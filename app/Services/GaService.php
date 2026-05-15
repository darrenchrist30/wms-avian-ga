<?php

namespace App\Services;

use App\Models\Cell;
use App\Models\GaRecommendation;
use App\Models\GaRecommendationDetail;
use App\Models\InboundOrder;
use App\Models\InboundOrderItem;
use App\Models\ItemAffinity;
use App\Models\Stock;
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
     *     { "cell_id": 10, "capacity_remaining": 50,
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
            ->whereIn('status', ['pending', 'partial_put_away'])
            ->where('quantity_received', '>', 0)
            ->get();

        if ($rawDetails->isEmpty()) {
            throw new \Exception(
                'Tidak ada item dengan quantity_received > 0. '
                    . 'Konfirmasi penerimaan fisik barang terlebih dahulu sebelum menjalankan GA.'
            );
        }

        // Sel-sel yang tersedia di warehouse ini. Zone tidak lagi dipakai.
        // HANYA cell MSpart (punya blok/grup/kolom/baris) — legacy cells tanpa koordinat
        // fisik dikecualikan agar GA tidak membuat rekomendasi ke lokasi yang tidak ada di denah.
        $cellsCollection = Cell::whereHas('rack', fn($q) => $q->where('warehouse_id', $order->warehouse_id))
            ->where('is_active', true)
            ->whereIn('status', ['available', 'partial'])
            ->where('capacity_used', '<', DB::raw('capacity_max'))
            ->whereNotNull('blok')
            ->whereNotNull('grup')
            ->whereNotNull('kolom')
            ->whereNotNull('baris')
            ->with('rack')
            ->get();

        // Konteks stok existing per cell (untuk preferensi continuity item/rack)
        $existingItemIdsByCell = DB::table('stock_records')
            ->select('cell_id', DB::raw('GROUP_CONCAT(DISTINCT item_id) as item_ids'))
            ->whereIn('cell_id', $cellsCollection->pluck('id'))
            ->where('quantity', '>', 0)
            ->whereIn('status', ['available', 'reserved'])
            ->groupBy('cell_id')
            ->pluck('item_ids', 'cell_id');

        $cellCandidates = collect($this->buildCellCandidates($cellsCollection, $existingItemIdsByCell));

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

            // Cari lokasi fisik existing yang sudah menyimpan item yang sama.
            // Untuk data MSpart, satu kandidat = satu blok + grup/baris rak + kolom.
            $existingCells = $cellCandidates
                ->filter(function (array $cell) use ($detail) {
                    return in_array((int) $detail->item_id, $cell['existing_item_ids'], true)
                        && ((int) $cell['capacity_remaining'] > 0);
                })
                ->sortBy(function (array $cell) {
                    return sprintf(
                        '%05d-%05d',
                        (int) ($cell['rack_index'] ?? 9999),
                        (int) ($cell['cell_index'] ?? 9999)
                    );
                })
                ->values();

            // 1) Buat chunk locked untuk existing cell terlebih dahulu
            foreach ($existingCells as $cell) {
                if ($remainingQty <= 0) {
                    break;
                }

                $cellRemaining = (int) $cell['capacity_remaining'];
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
                    'movement_type'     => $detail->item->movement_type,

                    // Ini yang membuat chunk ini wajib ke cell existing tersebut.
                    'preferred_cell_id' => $cell['cell_id'],
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
                    'movement_type'     => $detail->item->movement_type,

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

        $cells = $cellCandidates
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
    /**
     * Build GA candidates using the current warehouse model.
     *
     * Legacy cells stay one candidate per cells.id. MSpart cells are grouped into
     * one physical candidate per blok + grup + kolom, because grup is the rack row
     * and kolom is the operator-facing shelf column.
     */
    private function buildCellCandidates($cellsCollection, $existingItemIdsByCell): array
    {
        return $cellsCollection
            ->groupBy(fn(Cell $cell) => $this->physicalLocationKey($cell))
            ->map(function ($group) use ($existingItemIdsByCell) {
                $representative = $group
                    ->sortBy(fn(Cell $cell) => sprintf('%03d-%08d', (int) ($cell->baris ?? 0), $cell->id))
                    ->first();

                if (!$representative instanceof Cell) {
                    return null;
                }

                $cellIds = $group->pluck('id')->values()->all();
                $existingItemIds = $group
                    ->flatMap(function (Cell $cell) use ($existingItemIdsByCell) {
                        $itemIdsRaw = $existingItemIdsByCell->get($cell->id);

                        if (empty($itemIdsRaw)) {
                            return [];
                        }

                        return array_map('intval', explode(',', (string) $itemIdsRaw));
                    })
                    ->unique()
                    ->values()
                    ->all();

                if (!$this->isMspartCell($representative)) {
                    return [
                        'cell_id'              => $representative->id,
                        'rack_code'            => (string) ($representative->rack?->code ?? ''),
                        'rack_index'           => (int) ($representative->rack?->code ?? 9999),
                        'cell_code'            => (string) $representative->code,
                        'cell_index'           => $this->cellCodeToIndex($representative->code),
                        'dominant_category_id' => $representative->dominant_category_id,
                        'capacity_remaining'   => max(0, (int) $representative->capacity_max - (int) $representative->capacity_used),
                        'capacity_max'         => (int) $representative->capacity_max,
                        'status'               => $representative->status,
                        'existing_item_ids'    => $existingItemIds,
                    ];
                }

                $capacityMax = max(1, (int) ($group->max('capacity_max') ?: $representative->capacity_max ?: 20));
                $capacityUsed = DB::table('stock_records')
                    ->whereIn('cell_id', $cellIds)
                    ->where('quantity', '>', 0)
                    ->whereIn('status', ['available', 'reserved'])
                    ->count();
                $capacityRemaining = max(0, $capacityMax - $capacityUsed);
                $barisRak = $this->groupToRackRow((string) $representative->grup);
                $blok = (int) $representative->blok;
                $grup = strtoupper((string) $representative->grup);
                $kolom = (int) $representative->kolom;

                return [
                    'cell_id'                => $representative->id,
                    'rack_code'              => (string) ($representative->blok ?? $representative->rack?->code ?? ''),
                    'rack_index'             => $blok ?: (int) ($representative->rack?->code ?? 9999),
                    'cell_code'              => "{$blok}-{$grup}-K{$kolom}",
                    'cell_index'             => $kolom ?: 9999,
                    'dominant_category_id'   => $group->pluck('dominant_category_id')->filter()->first(),
                    'capacity_remaining'     => $capacityRemaining,
                    'capacity_max'           => $capacityMax,
                    'status'                 => $capacityUsed <= 0 ? 'available' : ($capacityRemaining <= 0 ? 'full' : 'partial'),
                    'existing_item_ids'      => $existingItemIds,
                    'is_mspart'              => true,
                    'blok'                   => $blok,
                    'grup'                   => $grup,
                    'baris_rak'              => $barisRak,
                    'kolom'                  => $kolom,
                    'physical_location_key'  => $this->physicalLocationKey($representative),
                    'physical_location_code' => $this->physicalLocationLabel($representative),
                    'physical_cell_ids'      => $cellIds,
                ];
            })
            ->filter(fn($cell) => $cell && (int) $cell['capacity_remaining'] > 0)
            ->values()
            ->all();
    }

    private function physicalLocationKey(Cell $cell): string
    {
        if ($this->isMspartCell($cell)) {
            return sprintf(
                'mspart:%d:%s:%d',
                (int) $cell->blok,
                strtoupper((string) $cell->grup),
                (int) $cell->kolom
            );
        }

        return 'cell:' . $cell->id;
    }

    private function physicalLocationLabel(Cell $cell): string
    {
        if (!$this->isMspartCell($cell)) {
            return (string) $cell->code;
        }

        $grup = strtoupper((string) $cell->grup);
        $barisRak = $this->groupToRackRow($grup);

        return "Blok {$cell->blok} - Grup {$grup} - Baris {$barisRak} - Kolom {$cell->kolom}";
    }

    private function isMspartCell(Cell $cell): bool
    {
        return $cell->blok !== null && $cell->grup !== null && $cell->kolom !== null;
    }

    private function groupToRackRow(string $grup): ?int
    {
        $index = strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ', strtoupper($grup));

        return $index === false ? null : $index + 1;
    }

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

        // Simpan detail per gen (per SKU).
        // GA memberi rekomendasi pada level KOLOM (1 dari 9 baris representatif).
        // Di sini kita translate ke baris spesifik berdasarkan aturan:
        //   1. Cari baris yang sudah ada SKU sama → consolidate stok
        //   2. Kalau tidak ada, ambil baris kosong dengan nomor terkecil (bottom-up)
        foreach ($gaResult['chromosome'] as $gene) {
            $itemId = (int) (InboundOrderItem::find($gene['inbound_detail_id'])?->item_id ?? 0);
            $assignedCellId = $this->assignSpecificBaris(
                (int) $gene['cell_id'],
                $itemId
            );

            GaRecommendationDetail::create([
                'ga_recommendation_id'   => $recommendation->id,
                'inbound_order_item_id'  => $gene['inbound_detail_id'],
                'cell_id'                => $assignedCellId,
                'quantity'               => $gene['quantity'],
                'gene_fitness'           => $gene['gene_fitness']  ?? null,
                'fc_cap_score'           => $gene['fc_cap']        ?? null,
                'fc_cat_score'           => $gene['fc_cat']        ?? null,
                'fc_aff_score'           => $gene['fc_aff']        ?? null,
                'fc_split_score'         => $gene['fc_split']      ?? null,
            ]);

        }

        $order->update(['processed_at' => now()]);

        return $recommendation->load('details');
    }

    /**
     * Translate rekomendasi GA (level kolom) → baris spesifik (level cell).
     *
     * Aturan placement dalam 1 kolom (9 baris):
     *   1. Prioritas: baris yang sudah ada SKU sama (consolidate stok existing)
     *   2. Fallback: baris kosong dengan nomor terkecil (bottom-up fill)
     *   3. Last resort: baris representatif dari GA (kalau kolom penuh, biar
     *      operator tetap dapat lokasi & bisa override manual)
     *
     * Untuk legacy cells (blok=NULL) — return as-is.
     */
    private function assignSpecificBaris(int $recommendedCellId, int $itemId): int
    {
        $cell = Cell::find($recommendedCellId);
        if (!$cell || $cell->blok === null) {
            return $recommendedCellId;
        }

        $columnCells = Cell::where('blok', $cell->blok)
            ->where('grup', $cell->grup)
            ->where('kolom', $cell->kolom)
            ->where('is_active', true)
            ->orderBy('baris')
            ->get();

        // Pre-load capacity_used per baris (count stock records aktif)
        $usedByCell = Stock::whereIn('cell_id', $columnCells->pluck('id'))
            ->where('quantity', '>', 0)
            ->whereIn('status', ['available', 'reserved'])
            ->select('cell_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('cell_id')
            ->pluck('cnt', 'cell_id');

        // Rule 1: baris yang sudah ada SKU sama
        if ($itemId > 0) {
            $cellWithSameSku = Stock::whereIn('cell_id', $columnCells->pluck('id'))
                ->where('item_id', $itemId)
                ->where('quantity', '>', 0)
                ->whereIn('status', ['available', 'reserved'])
                ->orderBy('cell_id')
                ->first();

            if ($cellWithSameSku) {
                $target = $columnCells->firstWhere('id', $cellWithSameSku->cell_id);
                if ($target && (int) ($usedByCell[$target->id] ?? 0) < (int) $target->capacity_max) {
                    return $target->id;
                }
            }
        }

        // Rule 2: baris kosong dengan nomor terkecil
        foreach ($columnCells as $bc) {
            $used = (int) ($usedByCell[$bc->id] ?? 0);
            if ($used < (int) $bc->capacity_max) {
                return $bc->id;
            }
        }

        // Rule 3: fallback ke baris representatif dari GA
        return $recommendedCellId;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC: Mock run (untuk testing sebelum Python FastAPI siap)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Ubah kode cell menjadi indeks posisi numerik dalam rack.
     * Contoh: "1-F" → 6 (F adalah huruf ke-6)
     *         "A"   → 1
     *         null  → 9999 (tidak diketahui)
     */
    private function cellCodeToIndex(?string $code): int
    {
        if (!$code) {
            return 9999;
        }

        $code = strtoupper(trim($code));

        // Format single letter: A, B, C, ...
        if (preg_match('/^[A-Z]$/', $code)) {
            return ord($code) - ord('A') + 1;
        }

        // Format "rack-cell": 1-F, 10-G, dll — ambil huruf setelah dash
        if (preg_match('/-([A-Z])$/', $code, $m)) {
            return ord($m[1]) - ord('A') + 1;
        }

        return 9999;
    }

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
            $cell = null;

            if (!empty($item['preferred_cell_id'])) {
                $cell = $cells->first(fn($c) => (int) $c['cell_id'] === (int) $item['preferred_cell_id']);
            }

            $cell ??= $cells->first(fn($c) => $c['capacity_remaining'] >= $item['quantity']);

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
