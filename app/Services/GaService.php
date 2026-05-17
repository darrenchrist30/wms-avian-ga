<?php

namespace App\Services;

use App\Models\Cell;
use App\Models\GaRecommendation;
use App\Models\GaRecommendationDetail;
use App\Models\InboundOrder;
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
    // PUBLIC: Run GA untuk banyak order sekaligus
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Jalankan GA untuk sekumpulan order secara sekuensial.
     *
     * Alur:
     *   1. Bangun payload order saat giliran order tersebut diproses.
     *   2. Kirim request ke Python FastAPI.
     *   3. Simpan hasil ke DB sebelum order berikutnya dibangun.
     *
     * Batch sengaja tidak paralel supaya accepted recommendation dari order
     * sebelumnya langsung menjadi slot reservation untuk order berikutnya.
     *
     * @param  InboundOrder[]  $orders
     * @return array  [order_id => ['rec' => GaRecommendation|null, 'error' => string|null]]
     */
    public function runBatch(array $orders, int $userId): array
    {
        if (empty($orders)) {
            return [];
        }

        Log::info('[GaService] Batch sekuensial dimulai', [
            'order_count' => count($orders),
            'order_ids'   => collect($orders)->pluck('id')->all(),
        ]);

        $results = [];

        foreach ($orders as $order) {
            try {
                // Build each payload after previous orders are persisted, so
                // accepted recommendations from this batch reserve slots for
                // the next orders.
                $payload = $this->buildPayload($order);
                $gaResult = $this->callPythonEngine($payload);

                $rec = DB::transaction(fn() => $this->persistResult(
                    $order, $userId, $gaResult, $payload['parameters']
                ));
                $results[$order->id] = ['rec' => $rec, 'error' => null];

                Log::info('[GaService] Batch: order selesai', [
                    'order_id'     => $order->id,
                    'do_number'    => $order->do_number,
                    'fitness'      => $gaResult['fitness_score'],
                    'generations'  => $gaResult['generations_run'] ?? null,
                    'exec_ms'      => $gaResult['execution_time_ms'] ?? null,
                ]);
            } catch (\Exception $e) {
                $results[$order->id] = ['rec' => null, 'error' => $e->getMessage()];
            }
        }

        return $results;
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
            ->whereIn('status', ['available', 'partial', 'full'])
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
            ->where('status', 'available')
            ->groupBy('cell_id')
            ->pluck('item_ids', 'cell_id');

        // Hitung jumlah slot stock record baru yang sudah di-reserve order lain,
        // bukan sum quantity fisik. Rekomendasi ke SKU yang sudah available di
        // cell yang sama tidak memakai slot baru karena put-away akan upsert.
        $reservedQtyByCell = DB::table('ga_recommendation_details as grd')
            ->join('ga_recommendations as gr', 'gr.id', '=', 'grd.ga_recommendation_id')
            ->join('inbound_transactions as it', 'it.id', '=', 'gr.inbound_order_id')
            ->join('inbound_details as idt', 'idt.id', '=', 'grd.inbound_order_item_id')
            ->leftJoin('stock_records as existing_stock', function ($join) {
                $join->on('existing_stock.cell_id', '=', 'grd.cell_id')
                    ->on('existing_stock.item_id', '=', 'idt.item_id')
                    ->where('existing_stock.quantity', '>', 0)
                    ->where('existing_stock.status', 'available');
            })
            ->select(
                'grd.cell_id',
                DB::raw('COUNT(DISTINCT CASE WHEN existing_stock.id IS NULL THEN grd.id END) as reserved_qty')
            )
            ->whereIn('grd.cell_id', $cellsCollection->pluck('id'))
            ->where('gr.status', 'accepted')
            ->where('it.warehouse_id', $order->warehouse_id)
            ->where('it.id', '!=', $order->id)
            ->whereIn('idt.status', ['pending', 'partial', 'partial_put_away'])
            ->groupBy('grd.cell_id')
            ->pluck('reserved_qty', 'cell_id');

        $cellCandidates = collect($this->buildCellCandidates(
            $cellsCollection,
            $existingItemIdsByCell,
            $reservedQtyByCell
        ));

        // ─────────────────────────────────────────────────────────────
        // One gene per inbound_detail.
        //
        // Capacity unit = stock record slot (bukan unit fisik).
        // Satu gene → satu stock record → satu slot kapasitas.
        // quantity_received (fisik) disimpan ke stock_records saat put-away;
        // di sini quantity hanya menjadi payload metadata untuk FC_SPLIT & display.
        //
        // Jika item sudah ada di cell tertentu → preferred_cell_id dikunci ke sana
        // agar GA memprioritaskan konsolidasi ke lokasi yang sudah ada.
        // ─────────────────────────────────────────────────────────────
        $items = [];

        foreach ($rawDetails as $detail) {
            // Cari cell existing untuk item ini (untuk preferred_cell_id lock)
            $existingCell = $cellCandidates
                ->filter(fn(array $cell) =>
                    in_array((int) $detail->item_id, $cell['existing_item_ids'], true)
                )
                ->sortBy(fn(array $cell) => sprintf(
                    '%05d-%05d',
                    (int) ($cell['rack_index'] ?? 9999),
                    (int) ($cell['cell_index'] ?? 9999)
                ))
                ->first();

            $items[] = [
                'inbound_detail_id' => $detail->id,
                'item_id'           => $detail->item_id,
                'sku'               => $detail->item->sku,
                'category_id'       => $detail->item->category_id,
                'quantity'          => (int) $detail->quantity_received,
                'item_size'         => $detail->item->item_size ?? 'medium',
                'movement_type'     => $detail->item->movement_type,
                'preferred_cell_id' => $existingCell ? $existingCell['cell_id'] : null,
            ];
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
                    . 'Pastikan ada sel MSpart aktif dengan slot kosong atau stok existing.'
            );
        }

        foreach ($items as $item) {
            $hasFeasibleCell = collect($cells)->contains(fn(array $cell) =>
                (int) $cell['capacity_remaining'] >= 1
                || in_array((int) $item['item_id'], $cell['existing_item_ids'] ?? [], true)
            );

            if (!$hasFeasibleCell) {
                throw new \Exception(
                    "Tidak ada slot tersedia untuk SKU {$item['sku']}. "
                        . 'Tambahkan cell kosong atau konsolidasikan stok existing terlebih dahulu.'
                );
            }
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
        $affinities = $this->mergeSkuFamilyAffinities(
            $affinities,
            $rawDetails->pluck('item')->unique('id')->values()
        );

        // Parameter GA adaptif: makin sedikit item → konvergen lebih cepat → parameter lebih ringan.
        // Dengan early stopping, GA berhenti begitu tidak ada perbaikan k generasi berturut-turut,
        // sehingga parameter batas atas (max_generations) jarang dicapai untuk order kecil.
        $itemCount = count($items);
        [$population, $maxGenerations, $earlyStopping] = match (true) {
            $itemCount <= 3  => [30,  50,  8],
            $itemCount <= 6  => [50,  80,  12],
            $itemCount <= 10 => [70,  120, 18],
            default          => [100, 150, 25],
        };

        return [
            'inbound_order_id' => $order->id,
            'items'            => $items,
            'cells'            => $cells,
            'affinities'       => $affinities,
            'parameters'       => [
                'population'       => $population,
                'max_generations'  => $maxGenerations,
                'mutation_rate'    => 0.15,
                'crossover_rate'   => 0.8,
                'elitism'          => 3,
                'early_stopping'   => $earlyStopping,
                'seed'             => (int) env('GA_SEED', 20260515),
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
     * Legacy cells stay one candidate per cells.id. MSpart cells use the exact
     * SQL coordinate: blok + grup + kolom + baris.
     */
    private function buildCellCandidates($cellsCollection, $existingItemIdsByCell, $reservedQtyByCell): array
    {
        return $cellsCollection
            ->groupBy(fn(Cell $cell) => $this->physicalLocationKey($cell))
            ->map(function ($group) use ($existingItemIdsByCell, $reservedQtyByCell) {
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

                $reservedQty = collect($cellIds)
                    ->sum(fn($cellId) => (int) ($reservedQtyByCell->get($cellId) ?? 0));

                if (!$this->isMspartCell($representative)) {
                    $capacityRemaining = max(
                        0,
                        (int) $representative->capacity_max - (int) $representative->capacity_used - $reservedQty
                    );

                    return [
                        'cell_id'              => $representative->id,
                        'rack_code'            => (string) ($representative->rack?->code ?? ''),
                        'rack_index'           => (int) ($representative->rack?->code ?? 9999),
                        'cell_code'            => (string) $representative->code,
                        'cell_index'           => $this->cellCodeToIndex($representative->code),
                        'dominant_category_id' => $representative->dominant_category_id,
                        'capacity_remaining'   => $capacityRemaining,
                        'capacity_max'         => (int) $representative->capacity_max,
                        'status'               => $representative->status,
                        'existing_item_ids'    => $existingItemIds,
                        'reserved_qty'         => $reservedQty,
                    ];
                }

                $capacityMax = max(1, (int) ($representative->capacity_max ?: 20));
                $capacityUsed = DB::table('stock_records')
                    ->whereIn('cell_id', $cellIds)
                    ->where('quantity', '>', 0)
                    ->whereIn('status', ['available', 'reserved'])
                    ->count();
                $capacityRemaining = max(0, $capacityMax - $capacityUsed - $reservedQty);
                $blok = (int) $representative->blok;
                $grup = strtoupper((string) $representative->grup);
                $kolom = (int) $representative->kolom;
                $baris = (int) $representative->baris;

                return [
                    'cell_id'                => $representative->id,
                    'rack_code'              => (string) ($representative->blok ?? $representative->rack?->code ?? ''),
                    'rack_index'             => $blok ?: (int) ($representative->rack?->code ?? 9999),
                    'cell_code'              => "{$blok}-{$grup}-{$kolom}-{$baris}",
                    'cell_index'             => ($kolom * 100) + $baris,
                    'dominant_category_id'   => $representative->dominant_category_id,
                    'capacity_remaining'     => $capacityRemaining,
                    'capacity_max'           => $capacityMax,
                    'status'                 => $capacityUsed <= 0 ? 'available' : ($capacityRemaining <= 0 ? 'full' : 'partial'),
                    'existing_item_ids'      => $existingItemIds,
                    'reserved_qty'           => $reservedQty,
                    'is_mspart'              => true,
                    'blok'                   => $blok,
                    'grup'                   => $grup,
                    'kolom'                  => $kolom,
                    'baris'                  => $baris,
                    'physical_location_key'  => $this->physicalLocationKey($representative),
                    'physical_location_code' => $this->physicalLocationLabel($representative),
                    'physical_cell_ids'      => $cellIds,
                ];
            })
            ->filter(fn($cell) =>
                $cell
                && (
                    (int) $cell['capacity_remaining'] > 0
                    || !empty($cell['existing_item_ids'])
                )
            )
            ->values()
            ->all();
    }

    private function physicalLocationKey(Cell $cell): string
    {
        if ($this->isMspartCell($cell)) {
            return sprintf(
                'mspart:%d:%s:%d:%d',
                (int) $cell->blok,
                strtoupper((string) $cell->grup),
                (int) $cell->kolom,
                (int) $cell->baris
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

        return "Blok {$cell->blok} - Grup {$grup} - Kolom {$cell->kolom} - Baris {$cell->baris}";
    }

    private function isMspartCell(Cell $cell): bool
    {
        return $cell->blok !== null
            && $cell->grup !== null
            && $cell->kolom !== null
            && $cell->baris !== null;
    }

    private function mergeSkuFamilyAffinities(array $affinities, $orderItems): array
    {
        $byPair = collect($affinities)
            ->keyBy(fn(array $affinity) => $this->affinityPairKey(
                (int) $affinity['item_id'],
                (int) $affinity['related_item_id']
            ))
            ->all();

        $items = collect($orderItems)->values();
        $count = $items->count();

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $itemA = $items[$i];
                $itemB = $items[$j];
                $familyA = $this->skuFamilyKey((string) $itemA->sku, (string) $itemA->name);
                $familyB = $this->skuFamilyKey((string) $itemB->sku, (string) $itemB->name);

                if (!$familyA || $familyA !== $familyB) {
                    continue;
                }

                $score = 0.85;
                $key = $this->affinityPairKey((int) $itemA->id, (int) $itemB->id);

                if (!isset($byPair[$key]) || (float) $byPair[$key]['affinity_score'] < $score) {
                    $byPair[$key] = [
                        'item_id'         => (int) $itemA->id,
                        'related_item_id' => (int) $itemB->id,
                        'affinity_score'  => $score,
                    ];
                }
            }
        }

        return array_values($byPair);
    }

    private function affinityPairKey(int $itemA, int $itemB): string
    {
        return min($itemA, $itemB) . ':' . max($itemA, $itemB);
    }

    private function skuFamilyKey(string $sku, string $name): ?string
    {
        $normalizedSku = strtoupper(trim($sku));
        $normalizedName = strtoupper(trim($name));
        $normalizedSku = preg_replace('/^03SP[-_]?/', '', $normalizedSku) ?: $normalizedSku;

        $rules = [
            '/^BE\d+/'         => 'BEARING',
            '/^BALLVA/'        => 'BALL_VALVE',
            '/^CHAPLING/'      => 'CHAIN_COUPLING',
            '/^DNG/'           => 'DOUBLE_NIPPLE',
            '/^LAMLED/'        => 'LED_LAMP',
            '/^(FO|FS|FU)\d+/' => 'FILTER',
            '/^BG/'            => 'BATU_GERINDA',
            '/^KLA/'           => 'KAWAT_LAS',
            '/^MCB/'           => 'MCB',
            '/^MB/'            => 'MUR_BAUT',
            '/^CAMLC/'         => 'CAMLOCK',
        ];

        foreach ($rules as $pattern => $family) {
            if (preg_match($pattern, $normalizedSku)) {
                return $family;
            }
        }

        if (strpos($normalizedName, 'FILTER') !== false || strpos($normalizedName, 'F.UDARA') !== false) {
            return 'FILTER';
        }

        $fallback = preg_replace('/[\d.\/-]+.*$/', '', $normalizedSku) ?: '';
        $fallback = trim($fallback, '-_ ');

        return strlen($fallback) >= 3 ? $fallback : null;
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

        // Simpan detail per gen (per SKU) pada cell SQL exact:
        // blok + grup + kolom + baris.
        $genes = collect($gaResult['chromosome'])
            ->groupBy(fn($gene) => $gene['inbound_detail_id'] . '|' . $gene['cell_id'])
            ->map(function ($group) {
                $first = $group->first();

                foreach (['gene_fitness', 'fc_cap', 'fc_cat', 'fc_aff', 'fc_split'] as $scoreKey) {
                    if ($group->contains(fn($gene) => isset($gene[$scoreKey]))) {
                        $first[$scoreKey] = round($group->avg(fn($gene) => $gene[$scoreKey] ?? 0), 4);
                    }
                }

                $first['quantity'] = $group->sum('quantity');

                return $first;
            })
            ->values();

        foreach ($genes as $gene) {
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

        }

        $order->update(['processed_at' => now()]);

        return $recommendation->load('details');
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

            $cell ??= $cells->first(fn($c) =>
                (int) $c['capacity_remaining'] >= 1
                || in_array((int) $item['item_id'], $c['existing_item_ids'] ?? [], true)
            );

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
