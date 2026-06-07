<?php

namespace App\Services;

use App\Models\Cell;
use App\Models\GaRecommendation;
use App\Services\CellCapacityService;
use App\Models\GaRecommendationDetail;
use App\Models\InboundOrder;
use App\Models\Item;
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
            ->with('item.category', 'item.homeCell')
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
            ->whereRaw('cells.baris <= (SELECT racks.total_levels FROM racks WHERE racks.id = cells.rack_id)')
            ->with('rack')
            ->get();

        $cellIds = $cellsCollection->pluck('id');

        // Konteks stok existing per cell (untuk preferensi continuity item/rack)
        $existingItemIdsByCell = DB::table('stock_records')
            ->select('cell_id', DB::raw('GROUP_CONCAT(DISTINCT item_id) as item_ids'))
            ->whereIn('cell_id', $cellIds)
            ->where('quantity', '>', 0)
            ->where('status', 'available')
            ->groupBy('cell_id')
            ->pluck('item_ids', 'cell_id');

        // Hitung kapasitas terpakai semua cell sekaligus (direct qty, bukan poin normalisasi).
        $usedCapacityByCell = DB::table('stock_records as sr')
            ->whereIn('sr.cell_id', $cellIds)
            ->where('sr.quantity', '>', 0)
            ->whereIn('sr.status', ['available', 'reserved'])
            ->groupBy('sr.cell_id')
            ->selectRaw('sr.cell_id, SUM(sr.quantity) as qty')
            ->pluck('qty', 'cell_id');

        // Hitung kapasitas yang sudah di-reserve rekomendasi order lain.
        $reservedQtyByCell = DB::table('ga_recommendation_details as grd')
            ->join('ga_recommendations as gr', 'gr.id', '=', 'grd.ga_recommendation_id')
            ->join('inbound_transactions as it', 'it.id', '=', 'gr.inbound_order_id')
            ->join('inbound_details as idt', 'idt.id', '=', 'grd.inbound_order_item_id')
            ->select(
                'grd.cell_id',
                DB::raw('SUM(grd.quantity) as reserved_qty')
            )
            ->whereIn('grd.cell_id', $cellIds)
            ->where('gr.status', 'accepted')
            ->where('it.warehouse_id', $order->warehouse_id)
            ->where('it.id', '!=', $order->id)
            ->whereIn('idt.status', ['pending', 'partial', 'partial_put_away'])
            ->groupBy('grd.cell_id')
            ->pluck('reserved_qty', 'cell_id');

        $cellCandidates = collect($this->buildCellCandidates(
            $cellsCollection,
            $existingItemIdsByCell,
            $reservedQtyByCell,
            $usedCapacityByCell
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
            $capacityDemand = app(CellCapacityService::class)->pointsForQuantity(
                $detail->item,
                (int) $detail->quantity_received
            );

            // Priority 1: item already has stock in a cell → consolidate there
            $existingCellsForItem = $cellCandidates
                ->filter(fn(array $cell) =>
                    in_array((int) $detail->item_id, $cell['existing_item_ids'], true)
                );

            $existingCell = $existingCellsForItem
                ->filter(fn(array $cell) => (int) $cell['capacity_remaining'] >= $capacityDemand)
                ->sortBy(fn(array $cell) => sprintf(
                    '%05d-%05d',
                    (int) ($cell['rack_index'] ?? 9999),
                    (int) ($cell['cell_index'] ?? 9999)
                ))
                ->first();

            // Priority 2: no existing stock → use MSpart home location (blok/grup/kolom/baris)
            // only when it does not conflict with an applied category zone.
            // If admin has already defined a feasible dominant-category zone,
            // that zone is stronger than an old neutral MSpart home cell.
            $preferredCellId = null;
            $hasFeasibleDominantZone = $this->hasFeasibleDominantCategoryZone(
                $detail->item,
                $cellCandidates,
                $capacityDemand
            );

            if ($existingCell) {
                $preferredCellId = $existingCell['cell_id'];
            } elseif ($existingCellsForItem->isEmpty() && $detail->item->home_cell_id) {
                $homeCell = $cellCandidates
                    ->first(fn(array $cell) =>
                        $cell['cell_id'] === $detail->item->home_cell_id
                        && (int) $cell['capacity_remaining'] >= $capacityDemand
                    );
                // MSpart home location is an anchor, not an absolute rule.
                // Neutral home cells are used only when no feasible applied
                // category zone exists for this item category.
                if ($homeCell && $this->isCategorySafePreferredCell($detail->item, $homeCell, $hasFeasibleDominantZone)) {
                    $preferredCellId = $homeCell['cell_id'];
                }
            }

            $items[] = [
                'inbound_detail_id' => $detail->id,
                'item_id'           => $detail->item_id,
                'sku'               => $detail->item->sku,
                'category_id'       => $detail->item->category_id,
                'quantity'          => (int) $detail->quantity_received,
                'capacity_demand'   => $capacityDemand,
                'item_size'         => $detail->item->item_size ?? 'medium',
                'movement_type'     => $detail->item->movement_type,
                'preferred_cell_id' => $preferredCellId,
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

        // Auto-split items whose capacity_demand exceeds every available cell.
        // Example: qty=120, max remaining cell=100 -> split: chunk(100)+chunk(20).
        // Each chunk shares the same inbound_detail_id; GA places them independently
        // and fc_split penalises the resulting multi-cell placement automatically.
        $maxCellCapacity = collect($cells)->max(fn($c) => (int) $c['capacity_remaining']) ?: CellCapacityService::DEFAULT_CAPACITY_MAX;
        $expandedItems   = [];

        foreach ($items as $item) {
            $demand = (int) $item['capacity_demand'];

            if ($demand <= $maxCellCapacity) {
                $expandedItems[] = $item;
                continue;
            }

            // Demand exceeds any single cell — split into same-size chunks that fit.
            $unitsPerChunk = max(1, (int) $maxCellCapacity);

            $remaining = (int) $item['quantity'];
            while ($remaining > 0) {
                $chunkQty    = min($remaining, $unitsPerChunk);
                $expandedItems[] = array_merge($item, [
                    'quantity'        => $chunkQty,
                    'capacity_demand' => $chunkQty,
                ]);
                $remaining -= $chunkQty;
            }
        }

        $items = $expandedItems;

        // Send only operationally relevant cells to Python. This preserves GA
        // quality because the removed cells are far/category-mismatched cells
        // that should only be considered after all meaningful candidates fail.
        $cells = $this->buildOperationalCellSubset($cells, $items);

        // Final feasibility check — each chunk must now fit in at least one cell.
        foreach ($items as $item) {
            $hasFeasibleCell = collect($cells)->contains(fn(array $cell) =>
                (int) $cell['capacity_remaining'] >= (int) $item['capacity_demand']
            );

            if (!$hasFeasibleCell) {
                throw new \Exception(
                    "Tidak ada kapasitas tersedia untuk SKU {$item['sku']} "
                        . "(butuh {$item['capacity_demand']} unit kapasitas). "
                        . 'Tambah kapasitas cell atau konsolidasikan stok existing terlebih dahulu.'
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
        // Populasi tetap 150 untuk semua ukuran order — memastikan coverage ruang
        // solusi yang memadai dengan inisialisasi acak murni (Holland, 1975).
        // Early stopping: berhenti jika tidak ada perbaikan selama 30–50 generasi.
        $itemCount = count($items);
        [$population, $maxGenerations, $earlyStopping] = match (true) {
            $itemCount <= 3  => [150, 500, 30],
            $itemCount <= 6  => [150, 300, 35],
            $itemCount <= 10 => [150, 200, 40],
            default          => [150, 150, 50],
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
                'seed'             => env('GA_SEED') !== null && env('GA_SEED') !== '' ? (int) env('GA_SEED') : null,
                'engine_driver'    => strtolower((string) config('services.ga_engine.driver', 'custom')),
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
    private function buildCellCandidates($cellsCollection, $existingItemIdsByCell, $reservedQtyByCell, $usedCapacityByCell = null): array
    {
        return $cellsCollection
            ->groupBy(fn(Cell $cell) => $this->physicalLocationKey($cell))
            ->map(function ($group) use ($existingItemIdsByCell, $reservedQtyByCell, $usedCapacityByCell) {
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

                $capacityMax = max(1, (int) ($representative->capacity_max ?: CellCapacityService::DEFAULT_CAPACITY_MAX));
                // Gunakan map yang sudah di-batch, fallback ke 0 jika cell kosong
                $capacityUsed = $usedCapacityByCell
                    ? (int) ($usedCapacityByCell->get($representative->id) ?? 0)
                    : app(CellCapacityService::class)->usedPoints($representative);
                $capacityRemaining = max(0, $capacityMax - $capacityUsed - $reservedQty);

                if (!$this->isMspartCell($representative)) {
                    return [
                        'cell_id'              => $representative->id,
                        'rack_code'            => (string) ($representative->rack?->code ?? ''),
                        'rack_index'           => (int) ($representative->rack?->code ?? 9999),
                        'cell_code'            => (string) $representative->code,
                        'cell_index'           => $this->cellCodeToIndex($representative->code),
                        'dominant_category_id' => $representative->dominant_category_id,
                        'capacity_remaining'   => $capacityRemaining,
                        'capacity_max'         => $capacityMax,
                        'status'               => $capacityUsed <= 0 ? 'available' : ($capacityRemaining <= 0 ? 'full' : 'partial'),
                        'existing_item_ids'    => $existingItemIds,
                        'reserved_qty'         => $reservedQty,
                    ];
                }

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

    private function buildOperationalCellSubset(array $cells, array $items): array
    {
        $byId = collect($cells)->keyBy(fn(array $cell) => (int) $cell['cell_id']);
        $selectedIds = collect();

        foreach ($items as $item) {
            $itemId = (int) $item['item_id'];
            $demand = (int) $item['capacity_demand'];
            $preferredCellId = $item['preferred_cell_id'] ?? null;
            $anchors = collect($cells)
                ->filter(fn(array $cell) => in_array($itemId, array_map('intval', $cell['existing_item_ids'] ?? []), true))
                ->values();
            $feasible = collect($cells)
                ->filter(fn(array $cell) => $this->cellFitsDemand($cell, $demand))
                ->values();
            $exactCategory = $feasible
                ->filter(fn(array $cell) => $this->isExactCategoryCandidate($item, $cell))
                ->values();
            $neutral = $feasible
                ->filter(fn(array $cell) => $this->isNeutralCandidate($cell))
                ->values();

            if ($preferredCellId && $byId->has((int) $preferredCellId)) {
                $selectedIds->push((int) $preferredCellId);
            }

            // Keep all same-SKU locations as anchors, including full cells.
            $selectedIds = $selectedIds->merge($anchors->pluck('cell_id')->map(fn($id) => (int) $id));

            $sameSkuFeasible = $feasible
                ->filter(fn(array $cell) => in_array($itemId, array_map('intval', $cell['existing_item_ids'] ?? []), true))
                ->values();
            $selectedIds = $selectedIds->merge($sameSkuFeasible->pluck('cell_id')->map(fn($id) => (int) $id));

            if ($anchors->isNotEmpty()) {
                $sameColumn = $feasible
                    ->filter(fn(array $cell) =>
                        $this->isCategorySafeCellForItem($item, $cell)
                        && $this->isSamePhysicalColumnAsAny($cell, $anchors)
                    )
                    ->values();
                $selectedIds = $selectedIds->merge($this->takeNearestCellIds($sameColumn, $anchors, 30));

                $sameBlockGroup = $exactCategory
                    ->filter(fn(array $cell) => $this->isSameBlockGroupAsAny($cell, $anchors))
                    ->values();
                $selectedIds = $selectedIds->merge($this->takeNearestCellIds($sameBlockGroup, $anchors, 80));

                $sameBlock = $exactCategory
                    ->filter(fn(array $cell) => $this->isSameBlockAsAny($cell, $anchors))
                    ->values();
                $selectedIds = $selectedIds->merge($this->takeNearestCellIds($sameBlock, $anchors, 120));

                $selectedIds = $selectedIds->merge($this->takeNearestCellIds($exactCategory, $anchors, 220));

                $neutralSameBlock = $neutral
                    ->filter(fn(array $cell) => $this->isSameBlockAsAny($cell, $anchors))
                    ->values();
                $selectedIds = $selectedIds->merge($this->takeNearestCellIds($neutralSameBlock, $anchors, 80));
                $selectedIds = $selectedIds->merge($this->takeNearestCellIds($neutral, $anchors, 120));

                // Last operational fallback: nearest feasible cells regardless
                // of category, kept small so category-safe options dominate.
                $selectedIds = $selectedIds->merge($this->takeNearestCellIds($feasible, $anchors, 80));
            } else {
                $selectedIds = $selectedIds->merge($this->takePositionCellIds($exactCategory, 220));
                $selectedIds = $selectedIds->merge($this->takePositionCellIds($neutral, 140));
                $selectedIds = $selectedIds->merge($this->takePositionCellIds($feasible, 80));
            }
        }

        return $byId
            ->only($selectedIds->unique()->values()->all())
            ->values()
            ->all();
    }

    private function cellFitsDemand(array $cell, int $demand): bool
    {
        return (int) ($cell['capacity_remaining'] ?? 0) >= $demand;
    }

    private function isExactCategoryCandidate(array $item, array $cell): bool
    {
        $itemId = (int) $item['item_id'];

        if (in_array($itemId, array_map('intval', $cell['existing_item_ids'] ?? []), true)) {
            return true;
        }

        return $item['category_id'] !== null
            && ($cell['dominant_category_id'] ?? null) !== null
            && (int) $item['category_id'] === (int) $cell['dominant_category_id'];
    }

    private function isNeutralCandidate(array $cell): bool
    {
        $dominantCategoryId = $cell['dominant_category_id'] ?? null;

        return $dominantCategoryId === null || $dominantCategoryId === '';
    }

    private function isCategorySafeCellForItem(array $item, array $cell): bool
    {
        return $this->isExactCategoryCandidate($item, $cell)
            || $this->isNeutralCandidate($cell);
    }

    private function takeNearestCellIds($cells, $anchors, int $limit)
    {
        return collect($cells)
            ->sortBy(fn(array $cell) => sprintf(
                '%08.2f-%06d-%06d-%08d',
                $this->nearestCellDistance($cell, $anchors),
                -1 * (int) ($cell['capacity_remaining'] ?? 0),
                (int) ($cell['cell_index'] ?? 9999),
                (int) $cell['cell_id']
            ))
            ->take($limit)
            ->pluck('cell_id')
            ->map(fn($id) => (int) $id)
            ->values();
    }

    private function takePositionCellIds($cells, int $limit)
    {
        return collect($cells)
            ->sortBy(fn(array $cell) => sprintf(
                '%06d-%06d-%06d-%08d',
                (int) ($cell['rack_index'] ?? 9999),
                (int) ($cell['cell_index'] ?? 9999),
                -1 * (int) ($cell['capacity_remaining'] ?? 0),
                (int) $cell['cell_id']
            ))
            ->take($limit)
            ->pluck('cell_id')
            ->map(fn($id) => (int) $id)
            ->values();
    }

    private function nearestCellDistance(array $cell, $anchors): float
    {
        $distances = collect($anchors)
            ->map(fn(array $anchor) => $this->cellDistance($cell, $anchor))
            ->filter(fn($distance) => $distance !== null);

        return $distances->isEmpty() ? 99999.0 : (float) $distances->min();
    }

    private function cellDistance(array $a, array $b): ?float
    {
        if (
            ($a['blok'] ?? null) === null || ($b['blok'] ?? null) === null
            || ($a['grup'] ?? null) === null || ($b['grup'] ?? null) === null
            || ($a['kolom'] ?? null) === null || ($b['kolom'] ?? null) === null
            || ($a['baris'] ?? null) === null || ($b['baris'] ?? null) === null
        ) {
            return null;
        }

        $grupA = ord(strtoupper((string) $a['grup'])[0]) - ord('A') + 1;
        $grupB = ord(strtoupper((string) $b['grup'])[0]) - ord('A') + 1;

        return abs((int) $a['blok'] - (int) $b['blok']) * 10
            + abs($grupA - $grupB) * 5
            + abs((int) $a['kolom'] - (int) $b['kolom']) * 2
            + abs((int) $a['baris'] - (int) $b['baris']);
    }

    private function isSamePhysicalColumnAsAny(array $cell, $anchors): bool
    {
        return collect($anchors)->contains(fn(array $anchor) =>
            ($cell['blok'] ?? null) === ($anchor['blok'] ?? null)
            && strtoupper((string) ($cell['grup'] ?? '')) === strtoupper((string) ($anchor['grup'] ?? ''))
            && ($cell['kolom'] ?? null) === ($anchor['kolom'] ?? null)
        );
    }

    private function isSameBlockGroupAsAny(array $cell, $anchors): bool
    {
        return collect($anchors)->contains(fn(array $anchor) =>
            ($cell['blok'] ?? null) === ($anchor['blok'] ?? null)
            && strtoupper((string) ($cell['grup'] ?? '')) === strtoupper((string) ($anchor['grup'] ?? ''))
        );
    }

    private function isSameBlockAsAny(array $cell, $anchors): bool
    {
        return collect($anchors)->contains(fn(array $anchor) =>
            ($cell['blok'] ?? null) === ($anchor['blok'] ?? null)
        );
    }

    private function hasFeasibleDominantCategoryZone(Item $item, $cellCandidates, int $capacityDemand): bool
    {
        if (!$item->category_id) {
            return false;
        }

        return collect($cellCandidates)->contains(fn(array $cell) =>
            (int) ($cell['capacity_remaining'] ?? 0) >= $capacityDemand
            && ($cell['dominant_category_id'] ?? null) !== null
            && (int) $cell['dominant_category_id'] === (int) $item->category_id
        );
    }

    private function isCategorySafePreferredCell(Item $item, array $cell, bool $categoryZoneAvailable = false): bool
    {
        $existingItemIds = array_map('intval', $cell['existing_item_ids'] ?? []);

        if (in_array((int) $item->id, $existingItemIds, true)) {
            return true;
        }

        $dominantCategoryId = $cell['dominant_category_id'] ?? null;

        if ($dominantCategoryId === null || $dominantCategoryId === '') {
            return !$categoryZoneAvailable;
        }

        return $item->category_id !== null
            && (int) $dominantCategoryId === (int) $item->category_id;
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
                $nameA = $this->normalizedItemName((string) $itemA->name);
                $nameB = $this->normalizedItemName((string) $itemB->name);
                $familyA = $this->skuFamilyKey((string) $itemA->sku, (string) $itemA->name);
                $familyB = $this->skuFamilyKey((string) $itemB->sku, (string) $itemB->name);
                $sameName = $nameA !== '' && $nameA === $nameB;
                $sameFamily = $familyA && $familyA === $familyB;

                if (!$sameName && !$sameFamily) {
                    continue;
                }

                $score = $sameName ? 0.95 : 0.85;
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

    private function normalizedItemName(string $name): string
    {
        $name = strtoupper(trim($name));
        $name = preg_replace('/\s+/', ' ', $name) ?: $name;

        return $name;
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

                foreach (['gene_fitness', 'fc_cap', 'fc_cat', 'fc_aff', 'fc_split', 'fc_mov'] as $scoreKey) {
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
                'fc_mov_score'           => $gene['fc_mov']        ?? null,
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
                (int) $c['capacity_remaining'] >= (int) $item['capacity_demand']
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
                'fc_cap'            => 24.0,
                'fc_cat'            => 21.0,
                'fc_aff'            => 14.0,
                'fc_split'          => 6.0,
                'fc_mov'            => 5.0,
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
