<?php

namespace App\Services;

use App\Models\Cell;
use App\Models\GaRecommendation;
use App\Models\GaRecommendationDetail;
use App\Models\InboundOrder;
use App\Models\InboundOrderItem;
use App\Models\ItemAffinity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GaService — Jembatan antara Laravel dan Python FastAPI GA Engine
 *
 * Tanggung jawab:
 *   1. Menyiapkan payload (item, cells, affinities) untuk dikirim ke Python
 *   2. Memanggil POST /ga/run di Python FastAPI
 *   3. Menerima hasil kromosom terbaik
 *   4. Menyimpan hasil ke ga_recommendations + ga_recommendation_details
 *   5. Mengupdate status inbound_transactions → 'recommended'
 *
 * Saat Python FastAPI belum siap, gunakan runMock() untuk testing flow.
 */
class GaService
{
    private string $baseUrl;
    private int    $timeoutSeconds;

    public function __construct()
    {
        $this->baseUrl        = config('services.ga_engine.url', 'http://127.0.0.1:8001');
        $this->timeoutSeconds = config('services.ga_engine.timeout', 120);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC: Run GA untuk satu InboundOrder
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Jalankan GA untuk inbound order yang diberikan.
     *
     * @param  InboundOrder $order   Order yang sudah status 'processing'
     * @param  int          $userId  Supervisor yang men-trigger
     * @return GaRecommendation      Record hasil GA yang tersimpan
     * @throws \Exception            Jika GA engine tidak bisa dihubungi atau hasilnya invalid
     */
    public function run(InboundOrder $order, int $userId): GaRecommendation
    {
        Log::info('[GaService] Memulai GA run', [
            'inbound_order_id' => $order->id,
            'do_number'        => $order->do_number,
            'triggered_by'     => $userId,
        ]);

        // 1. Siapkan payload untuk Python
        $payload = $this->buildPayload($order);

        Log::info('[GA PAYLOAD DEBUG]', [
            'items' => $payload['items'],
            'cells_with_existing_items' => collect($payload['cells'])
                ->filter(fn($c) => !empty($c['existing_item_ids']))
                ->values()
                ->toArray(),
        ]);

        // 2. Panggil Python FastAPI
        $gaResult = $this->callPythonEngine($payload);

        // 3. Simpan hasil ke database dalam satu transaksi
        return DB::transaction(function () use ($order, $userId, $gaResult, $payload) {
            return $this->persistResult($order, $userId, $gaResult, $payload['parameters']);
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE: Build Payload
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Bangun payload JSON yang akan dikirim ke Python FastAPI.
     *
     * Format payload:
     * {
     *   "inbound_order_id": 1,
     *   "items": [
     *     { "inbound_detail_id": 1, "item_id": 5, "sku": "SKU-001",
     *       "category_id": 2, "quantity": 100, "item_size": "medium",
     *       "movement_type": "fast_moving" }
     *   ],
     *   "cells": [
     *     { "cell_id": 10, "zone_category": "A", "capacity_remaining": 50,
     *       "capacity_max": 200, "status": "available" }
     *   ],
     *   "affinities": [
     *     { "item_id": 5, "related_item_id": 8, "affinity_score": 0.75 }
     *   ],
     *   "parameters": {
     *     "population": 100, "max_generations": 150,
     *     "mutation_rate": 0.15, "elitism": 3,
     *     "early_stopping": 20
     *   }
     * }
     */
    private function buildPayload(InboundOrder $order): array
    {
        // Item-item yang akan diproses (hanya yang belum put_away)
        // Ambil detail item mentah dulu.
        // Quantity akan dipecah setelah sistem mengetahui existing cell dan kapasitasnya.
        $rawDetails = $order->items()
            ->with('item.category')
            ->whereIn('status', ['pending', 'recommended', 'partial_put_away'])
            ->where('quantity_received', '>', 0)
            ->get();

        if ($rawDetails->isEmpty()) {
            throw new \Exception(
                'Tidak ada item dengan quantity_received > 0. '
                    . 'Konfirmasi penerimaan fisik barang terlebih dahulu sebelum menjalankan GA.'
            );
        }

        // Sel-sel yang tersedia di warehouse ini
        // Gunakan rack.zone sebagai fallback karena warehouse_id di rack bisa null
        $cellsCollection = Cell::where(function ($q) use ($order) {
            $q->whereHas('rack', fn($q2) => $q2->where('warehouse_id', $order->warehouse_id))
                ->orWhereHas('rack.zone', fn($q2) => $q2->where('warehouse_id', $order->warehouse_id));
        })
            ->where('is_active', true)
            ->whereIn('status', ['available', 'partial'])
            ->where('capacity_used', '<', DB::raw('capacity_max'))
            ->with('rack.zone')
            ->get();

        // Konteks stok existing per cell (untuk preferensi continuity item/rack)
        $existingItemIdsByCell = DB::table('stock_records')
            ->select('cell_id', DB::raw('GROUP_CONCAT(DISTINCT item_id) as item_ids'))
            ->whereIn('cell_id', $cellsCollection->pluck('id'))
            ->where('quantity', '>', 0)
            ->whereIn('status', ['available', 'reserved'])
            ->groupBy('cell_id')
            ->pluck('item_ids', 'cell_id');

        // ─────────────────────────────────────────────────────────────
        // Partial allocation:
        // Isi existing cell yang sudah menyimpan item tersebut terlebih dahulu.
        // Jika masih ada sisa quantity, sisanya dikirim ke GA sebagai chunk bebas.
        // Contoh: SP-BRG-001 qty 35, cell 1-D sisa 10
        // → chunk 10 dikunci ke 1-D, sisa 25 bebas dicari GA.
        // ─────────────────────────────────────────────────────────────
        $items = [];

        foreach ($rawDetails as $detail) {
            $remainingQty = (int) $detail->quantity_received;

            // Cari cell existing yang sudah menyimpan item yang sama dan masih punya sisa kapasitas.
            $existingCells = $cellsCollection
                ->filter(function (Cell $cell) use ($detail, $existingItemIdsByCell) {
                    $itemIdsRaw = $existingItemIdsByCell->get($cell->id);

                    $existingIds = $itemIdsRaw
                        ? array_values(array_map('intval', explode(',', (string) $itemIdsRaw)))
                        : [];

                    return in_array((int) $detail->item_id, $existingIds, true)
                        && (($cell->capacity_max - $cell->capacity_used) > 0);
                })
                ->sortBy(function (Cell $cell) {
                    return sprintf(
                        '%05d-%s',
                        (int) ($cell->rack?->code ?? 9999),
                        $cell->code
                    );
                })
                ->values();

            // 1) Buat chunk locked untuk existing cell terlebih dahulu
            foreach ($existingCells as $cell) {
                if ($remainingQty <= 0) {
                    break;
                }

                $cellRemaining = (int) ($cell->capacity_max - $cell->capacity_used);
                $chunkQty = min($remainingQty, $cellRemaining);

                if ($chunkQty <= 0) {
                    continue;
                }

                $items[] = [
                    'inbound_detail_id' => $detail->id,
                    'item_id'           => $detail->item_id,
                    'sku'               => $detail->item->sku,
                    'category_id'       => $detail->item->category_id,
                    'quantity'          => $chunkQty,
                    'item_size'         => $detail->item->item_size ?? 'medium',
                    'movement_type'     => $detail->item->movement_type ?? 'slow_moving',

                    // Ini yang membuat chunk ini wajib ke cell existing tersebut.
                    'preferred_cell_id' => $cell->id,
                ];

                $remainingQty -= $chunkQty;
            }

            // 2) Jika masih ada sisa, kirim sebagai chunk bebas ke GA
            if ($remainingQty > 0) {
                $items[] = [
                    'inbound_detail_id' => $detail->id,
                    'item_id'           => $detail->item_id,
                    'sku'               => $detail->item->sku,
                    'category_id'       => $detail->item->category_id,
                    'quantity'          => $remainingQty,
                    'item_size'         => $detail->item->item_size ?? 'medium',
                    'movement_type'     => $detail->item->movement_type ?? 'slow_moving',

                    // Null berarti GA bebas memilih cell terbaik.
                    'preferred_cell_id' => null,
                ];
            }
        }

        if (empty($items)) {
            throw new \Exception(
                'Tidak ada item yang dapat diproses GA setelah partial allocation.'
            );
        }

        $cells = $cellsCollection
            ->map(function (Cell $cell) use ($existingItemIdsByCell) {
                $itemIdsRaw = $existingItemIdsByCell->get($cell->id);
                $existingItemIds = [];
                if (!empty($itemIdsRaw)) {
                    $existingItemIds = array_values(array_map('intval', explode(',', (string) $itemIdsRaw)));
                }

                return [
                    'cell_id'              => $cell->id,
                    // zone_category: gunakan field langsung jika ada, fallback ke kode zona
                    'zone_category'        => $cell->zone_category ?? $cell->rack?->zone?->code,
                    'rack_code'            => (string) ($cell->rack?->code ?? ''),
                    'dominant_category_id' => $cell->dominant_category_id,
                    'capacity_remaining'   => $cell->capacity_max - $cell->capacity_used,
                    'capacity_max'         => $cell->capacity_max,
                    'status'               => $cell->status,
                    'existing_item_ids'    => $existingItemIds,
                ];
            })
            ->values()
            ->toArray();

        if (empty($cells)) {
            throw new \Exception(
                'Tidak ada sel tersedia di warehouse ini. '
                    . 'Pastikan ada sel dengan status available atau partial.'
            );
        }

        // Ambil semua affinitas yang relevan (melibatkan item-item dalam order ini)
        $itemIds = collect($items)->pluck('item_id')->unique()->toArray();
        $affinities = ItemAffinity::where(function ($q) use ($itemIds) {
            $q->whereIn('item_id', $itemIds)
                ->orWhereIn('related_item_id', $itemIds);
        })
            ->where('affinity_score', '>', 0)
            ->get()
            ->map(fn($a) => [
                'item_id'         => $a->item_id,
                'related_item_id' => $a->related_item_id,
                'affinity_score'  => (float) $a->affinity_score,
            ])
            ->values()
            ->toArray();

        return [
            'inbound_order_id' => $order->id,
            'items'            => $items,
            'cells'            => $cells,
            'affinities'       => $affinities,
            'parameters'       => [
                'population'       => 100,
                'max_generations'  => 150,
                'mutation_rate'    => 0.15,
                'crossover_rate'   => 0.8,
                'elitism'          => 3,
                'early_stopping'   => 20,
                'seed' => null,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE: Call Python FastAPI
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Kirim payload ke Python FastAPI dan terima hasil kromosom terbaik.
     *
     * Expected response dari Python:
     * {
     *   "fitness_score": 87.45,
     *   "generations_run": 98,
     *   "execution_time_ms": 4230,
     *   "chromosome": [
     *     {
     *       "inbound_detail_id": 1,
     *       "cell_id": 10,
     *       "quantity": 100,
     *       "gene_fitness": 85.2,
     *       "fc_cap": 38.0,
     *       "fc_cat": 28.5,
     *       "fc_aff": 12.0,
     *       "fc_split": 6.7
     *     }
     *   ]
     * }
     */
    private function callPythonEngine(array $payload): array
    {
        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$this->baseUrl}/ga/run", $payload);

            if ($response->failed()) {
                $errorBody = $response->json('detail') ?? $response->body();
                throw new \Exception("GA Engine error [{$response->status()}]: {$errorBody}");
            }

            $result = $response->json();

            // Validasi respons minimum
            if (empty($result['chromosome']) || !isset($result['fitness_score'])) {
                throw new \Exception('Respons GA Engine tidak valid: chromosome atau fitness_score kosong.');
            }

            Log::info('[GaService] GA selesai', [
                'fitness_score'    => $result['fitness_score'],
                'generations_run'  => $result['generations_run'] ?? null,
                'execution_time_ms' => $result['execution_time_ms'] ?? null,
                'total_genes'      => count($result['chromosome']),
            ]);

            return $result;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new \Exception(
                "GA Engine tidak dapat dihubungi di {$this->baseUrl}. "
                    . "Pastikan Python FastAPI sudah berjalan. Detail: {$e->getMessage()}"
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE: Persist Result
    // ─────────────────────────────────────────────────────────────────────────

    private function persistResult(
        InboundOrder $order,
        int $userId,
        array $gaResult,
        ?array $parameters = null
    ): GaRecommendation {
        // Simpan header GA run
        $recommendation = GaRecommendation::create([
            'inbound_order_id'  => $order->id,
            'generated_by'      => $userId,
            'chromosome_json'   => $gaResult['chromosome'],
            'fitness_score'     => $gaResult['fitness_score'],
            'generations_run'   => $gaResult['generations_run'] ?? 0,
            'execution_time_ms' => $gaResult['execution_time_ms'] ?? null,
            'parameters_json'   => $parameters ?? ($gaResult['parameters'] ?? null),
            'generated_at'      => now(),
            'status'            => 'pending',
        ]);

        // Simpan detail per gen (per SKU)
        foreach ($gaResult['chromosome'] as $gene) {
            GaRecommendationDetail::create([
                'ga_recommendation_id'   => $recommendation->id,
                'inbound_order_item_id'  => $gene['inbound_detail_id'],
                'cell_id'                => $gene['cell_id'],
                'quantity'               => $gene['quantity'],
                'gene_fitness'           => $gene['gene_fitness']  ?? null,
                'fc_cap_score'           => $gene['fc_cap']        ?? null,
                'fc_cat_score'           => $gene['fc_cat']        ?? null,
                'fc_aff_score'           => $gene['fc_aff']        ?? null,
                'fc_split_score'         => $gene['fc_split']      ?? null,
            ]);

            // Update status inbound_detail → 'recommended'
            InboundOrderItem::where('id', $gene['inbound_detail_id'])
                ->update(['status' => 'recommended']);
        }

        // Update status order → 'recommended'
        $order->update([
            'status'       => 'recommended',
            'processed_at' => now(),
        ]);

        return $recommendation->load('details');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC: Mock run (untuk testing sebelum Python FastAPI siap)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Mock GA run — assign item ke sel secara random dengan fitness dummy.
     * Gunakan ini untuk testing alur put-away tanpa Python.
     */
    public function runMock(InboundOrder $order, int $userId): GaRecommendation
    {
        Log::info('[GaService] Mock GA run', ['inbound_order_id' => $order->id]);

        $payload = $this->buildPayload($order);

        // Buat kromosom dummy: assign setiap item ke sel pertama yang muat
        $cells     = collect($payload['cells'])->sortByDesc('capacity_remaining');
        $chromosome = [];

        foreach ($payload['items'] as $item) {
            // Cari sel pertama yang kapasitasnya cukup
            $cell = $cells->first(fn($c) => $c['capacity_remaining'] >= $item['quantity']);

            if (!$cell) {
                // Tidak ada sel yang muat, pakai yang paling besar sisa kapasitasnya
                $cell = $cells->first();
            }

            $chromosome[] = [
                'inbound_detail_id' => $item['inbound_detail_id'],
                'cell_id'           => $cell['cell_id'],
                'quantity'          => $item['quantity'],
                'gene_fitness'      => 70.0,
                'fc_cap'            => 28.0,
                'fc_cat'            => 21.0,
                'fc_aff'            => 14.0,
                'fc_split'          => 7.0,
            ];
        }

        $mockResult = [
            'fitness_score'     => 70.0,
            'generations_run'   => 0,
            'execution_time_ms' => 0,
            'chromosome'        => $chromosome,
        ];

        return DB::transaction(fn() => $this->persistResult(
            $order,
            $userId,
            $mockResult,
            $payload['parameters']
        ));
    }
}
