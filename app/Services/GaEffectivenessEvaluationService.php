<?php

namespace App\Services;

use App\Models\Cell;
use App\Models\GaRecommendation;
use App\Models\InboundOrder;
use App\Models\InboundOrderItem;
use App\Models\ItemAffinity;
use App\Models\Stock;
use Illuminate\Support\Collection;

class GaEffectivenessEvaluationService
{
    private const W_BLOK = 10;
    private const W_GRUP = 5;
    private const W_KOLOM = 2;
    private const W_BARIS = 1;

    private const BASE_TIME_PER_ITEM_SECONDS = 60;
    private const TRAVEL_TIME_PER_DISTANCE_UNIT_SECONDS = 5;

    private const FC_CAP = 30;
    private const FC_CAT = 25;
    private const FC_AFF = 20;
    private const FC_SPLIT = 15;
    private const FC_MOV = 10;

    public function __construct(private readonly CellCapacityService $capacityService)
    {
    }

    public function evaluate(array $orderIds, int $randomSeed = 42): array
    {
        $orderIds = collect($orderIds)
            ->filter(fn($id) => is_numeric($id))
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $orders = InboundOrder::with([
                'warehouse',
                'items.item.category',
                'items.item.unit',
                'items.item.homeCell.rack',
            ])
            ->whereIn('id', $orderIds)
            ->orderBy('do_date')
            ->orderBy('do_number')
            ->get();

        $details = $orders
            ->flatMap(function (InboundOrder $order) {
                $order->items->each(fn(InboundOrderItem $detail) => $detail->setRelation('inboundOrder', $order));
                return $order->items;
            })
            ->filter(fn(InboundOrderItem $detail) => $detail->item && (int) $detail->quantity_received > 0)
            ->values();

        if ($orders->isEmpty() || $details->isEmpty()) {
            return $this->emptyResult($orders, $randomSeed);
        }

        $warehouseIds = $orders->pluck('warehouse_id')->filter()->unique()->values();
        $itemIds = $details->pluck('item_id')->unique()->values();

        $cells = Cell::with(['rack', 'dominantCategory'])
            ->where('is_active', true)
            ->where('status', '!=', 'blocked')
            ->where(fn($q) => $q->whereNotNull('baris')->orWhereNull('blok'))
            ->whereHas('rack', fn($q) => $q->whereIn('warehouse_id', $warehouseIds))
            ->orderBy('blok')
            ->orderBy('grup')
            ->orderBy('kolom')
            ->orderBy('baris')
            ->get();

        $cellsById = $cells->keyBy('id');
        $cellSnapshot = $this->buildCellSnapshot($cells);
        $existingStocks = $this->loadExistingStocks($itemIds, $warehouseIds);
        $latestGa = $this->loadLatestGaRecommendations($orderIds);
        $affinityMap = $this->loadAffinityMap($itemIds);

        $existingAssignments = $this->buildExistingAssignments($details, $existingStocks, $cells, $cellSnapshot);
        $randomAssignments = $this->buildRandomAssignments($details, $cells, $cellSnapshot, $randomSeed);
        $gaAssignments = $this->buildGaAssignments($details, $latestGa, $cellsById, $cellSnapshot);

        $scenarios = [
            'existing' => $this->scoreScenario('existing', 'Existing / Manual', $existingAssignments, $cellsById, $cellSnapshot, $existingStocks, $affinityMap),
            'random' => $this->scoreScenario('random', 'Random Placement', $randomAssignments, $cellsById, $cellSnapshot, $existingStocks, $affinityMap),
            'ga' => $this->scoreScenario('ga', 'Genetic Algorithm', $gaAssignments, $cellsById, $cellSnapshot, $existingStocks, $affinityMap),
        ];

        return [
            'has_result' => true,
            'scope' => [
                'orders' => $orders,
                'order_ids' => $orderIds,
                'do_numbers' => $orders->pluck('do_number')->values()->all(),
                'item_line_count' => $details->count(),
                'sku_count' => $details->pluck('item_id')->unique()->count(),
                'total_qty' => (int) $details->sum('quantity_received'),
                'random_seed' => $randomSeed,
            ],
            'scenarios' => $scenarios,
            'summary_rows' => $this->buildSummaryRows($scenarios),
            'detail_rows' => $this->buildDetailRows($details, $scenarios),
            'notes' => $this->collectNotes($scenarios),
            'narrative' => $this->buildNarrative($scenarios),
        ];
    }

    public function distanceBetweenCells(?Cell $a, ?Cell $b): float
    {
        if (!$a || !$b) {
            return 0.0;
        }

        if ($a->blok !== null && $b->blok !== null) {
            return abs((int) $a->blok - (int) $b->blok) * self::W_BLOK
                + abs($this->groupIndex($a->grup) - $this->groupIndex($b->grup)) * self::W_GRUP
                + abs((int) ($a->kolom ?? 1) - (int) ($b->kolom ?? 1)) * self::W_KOLOM
                + abs((int) ($a->baris ?? 1) - (int) ($b->baris ?? 1)) * self::W_BARIS;
        }

        return abs((int) ($a->rack_id ?? 0) - (int) ($b->rack_id ?? 0)) * self::W_BLOK
            + abs((int) ($a->column ?? 1) - (int) ($b->column ?? 1)) * self::W_KOLOM
            + abs((int) ($a->level ?? 1) - (int) ($b->level ?? 1)) * self::W_BARIS;
    }

    public function calculateImprovement(float|int|null $baseline, float|int|null $gaValue, bool $lowerIsBetter): array
    {
        $baseline = $baseline === null ? null : (float) $baseline;
        $gaValue = $gaValue === null ? null : (float) $gaValue;

        if ($baseline === null || $gaValue === null) {
            return ['value' => null, 'label' => '-', 'class' => 'text-muted'];
        }

        if (abs($baseline) < 0.000001) {
            if (abs($gaValue) < 0.000001) {
                return ['value' => 0.0, 'label' => 'Tidak berubah', 'class' => 'text-muted'];
            }

            $isBetter = $lowerIsBetter ? false : true;
            return [
                'value' => null,
                'label' => $isBetter ? 'Lebih baik' : 'Lebih buruk',
                'class' => $isBetter ? 'text-success' : 'text-danger',
            ];
        }

        $value = $lowerIsBetter
            ? (($baseline - $gaValue) / $baseline) * 100
            : (($gaValue - $baseline) / $baseline) * 100;

        return [
            'value' => round($value, 2),
            'label' => number_format($value, 2) . '%',
            'class' => $value >= 0 ? 'text-success' : 'text-danger',
        ];
    }

    private function emptyResult(Collection $orders, int $randomSeed): array
    {
        return [
            'has_result' => false,
            'scope' => [
                'orders' => $orders,
                'order_ids' => $orders->pluck('id')->all(),
                'do_numbers' => $orders->pluck('do_number')->all(),
                'item_line_count' => 0,
                'sku_count' => 0,
                'total_qty' => 0,
                'random_seed' => $randomSeed,
            ],
            'scenarios' => [],
            'summary_rows' => [],
            'detail_rows' => [],
            'notes' => ['Pilih minimal satu inbound order yang memiliki item diterima.'],
            'narrative' => null,
        ];
    }

    private function buildCellSnapshot(Collection $cells): array
    {
        $stockRows = Stock::with(['item'])
            ->whereIn('cell_id', $cells->pluck('id'))
            ->where('quantity', '>', 0)
            ->whereIn('status', ['available', 'reserved'])
            ->get()
            ->groupBy('cell_id');

        $snapshot = [];
        foreach ($cells as $cell) {
            $rows = $stockRows->get($cell->id, collect());
            $itemIds = $rows->pluck('item_id')->unique()->values()->all();
            $categoryCounts = [];
            foreach ($rows as $row) {
                $categoryId = $row->item?->category_id;
                if ($categoryId) {
                    $categoryCounts[$categoryId] = ($categoryCounts[$categoryId] ?? 0) + (int) $row->quantity;
                }
            }

            arsort($categoryCounts);
            $dominantCategoryId = (int) ($cell->dominant_category_id ?: (array_key_first($categoryCounts) ?: 0));
            $itemQuantities = $rows
                ->groupBy('item_id')
                ->map(fn(Collection $itemRows) => (int) $itemRows->sum('quantity'))
                ->all();
            $usedPoints = (int) $rows->sum(function (Stock $row) {
                return $this->capacityService->pointsForQuantity($row->item, (int) $row->quantity);
            });
            $capacityMax = $this->capacityService->capacityMax($cell);

            $snapshot[$cell->id] = [
                'capacity_max' => $capacityMax,
                'capacity_used' => $usedPoints,
                'capacity_remaining' => max(0, $capacityMax - $usedPoints),
                'existing_item_ids' => $itemIds,
                'item_quantities' => $itemQuantities,
                'dominant_category_id' => $dominantCategoryId ?: null,
                'warehouse_id' => $cell->rack?->warehouse_id,
            ];
        }

        return $snapshot;
    }

    private function loadExistingStocks(Collection $itemIds, Collection $warehouseIds): Collection
    {
        return Stock::with(['cell.rack', 'item.category'])
            ->whereIn('item_id', $itemIds)
            ->whereIn('warehouse_id', $warehouseIds)
            ->where('quantity', '>', 0)
            ->whereIn('status', ['available', 'reserved'])
            ->whereHas('cell', fn($q) => $q->where('is_active', true))
            ->get()
            ->groupBy('item_id');
    }

    private function loadLatestGaRecommendations(array $orderIds): Collection
    {
        return GaRecommendation::with(['details.cell.rack', 'details.inboundOrderItem'])
            ->whereIn('inbound_order_id', $orderIds)
            ->whereIn('status', ['pending', 'accepted', 'pending_review'])
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('inbound_order_id')
            ->map(fn(Collection $rows) => $rows->first());
    }

    private function loadAffinityMap(Collection $itemIds): array
    {
        $rows = ItemAffinity::whereIn('item_id', $itemIds)
            ->orWhereIn('related_item_id', $itemIds)
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->item_id][$row->related_item_id] = (float) $row->affinity_score;
            $map[$row->related_item_id][$row->item_id] = (float) $row->affinity_score;
        }

        return $map;
    }

    private function buildExistingAssignments(Collection $details, Collection $existingStocks, Collection $cells, array $cellSnapshot): array
    {
        $assignments = [];

        foreach ($details as $detail) {
            $item = $detail->item;
            $stocks = $existingStocks->get($item->id, collect())
                ->filter(fn(Stock $stock) => (int) $stock->warehouse_id === (int) $detail->inboundOrder?->warehouse_id);

            $targetCell = $stocks
                ->pluck('cell')
                ->filter()
                ->unique('id')
                ->sortByDesc(fn(Cell $cell) => in_array($item->id, $cellSnapshot[$cell->id]['existing_item_ids'] ?? [], true))
                ->first();

            $notes = [];
            $source = 'stock_records';

            if (!$targetCell && $item->homeCell) {
                $targetCell = $item->homeCell;
                $source = 'home_cell_id';
                $notes[] = 'Tidak ada stock_records aktif; memakai home_cell_id.';
            }

            if (!$targetCell) {
                $targetCell = $this->nearestAvailableCell($cells, null, $detail, $cellSnapshot);
                $source = 'fallback';
                $notes[] = 'Tidak ada existing location atau home_cell_id; memakai fallback cell aktif agar evaluasi tetap berjalan.';
            }

            $assignments[$detail->id] = $this->baseAssignment($detail, $notes);
            if ($targetCell) {
                $assignments[$detail->id]['placements'][] = $this->makePlacement(
                    $detail,
                    $targetCell,
                    (int) $detail->quantity_received,
                    $source,
                    $this->demandForCellSnapshot($item, (int) $detail->quantity_received, $targetCell, $cellSnapshot)
                );
            } else {
                $assignments[$detail->id]['notes'][] = 'Unassigned: tidak ada cell kandidat aktif.';
            }

            $assignments[$detail->id]['reference_cell_ids'] = $stocks
                ->pluck('cell_id')
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return $assignments;
    }

    private function buildRandomAssignments(Collection $details, Collection $cells, array $cellSnapshot, int $seed): array
    {
        $assignments = [];
        mt_srand($seed);

        foreach ($details->sortBy('id') as $detail) {
            $item = $detail->item;
            $qty = (int) $detail->quantity_received;

            // Baseline random murni: hanya menjaga gudang yang sama dan cell aktif.
            // Kapasitas, kategori, afinitas, split, dan movement tidak dipakai saat memilih.
            $candidateCells = $cells
                ->filter(fn(Cell $cell) => (int) $cell->rack?->warehouse_id === (int) $detail->inboundOrder?->warehouse_id)
                ->values();

            $assignments[$detail->id] = $this->baseAssignment($detail);
            if ($candidateCells->isEmpty()) {
                $assignments[$detail->id]['notes'][] = 'Random unassigned: tidak ada cell aktif pada warehouse order.';
                continue;
            }

            $idx = mt_rand(0, $candidateCells->count() - 1);
            $cell = $candidateCells->get($idx);
            $demand = $this->demandForCellSnapshot($item, $qty, $cell, $cellSnapshot);

            $assignments[$detail->id]['placements'][] = $this->makePlacement($detail, $cell, $qty, 'random_unconstrained', $demand);
            $assignments[$detail->id]['notes'][] = 'Random baseline memilih cell aktif secara acak tanpa mempertimbangkan kapasitas, kategori, afinitas, split, atau movement.';
        }

        return $assignments;
    }

    private function buildGaAssignments(Collection $details, Collection $latestGa, Collection $cellsById, array $cellSnapshot): array
    {
        $assignments = [];

        foreach ($details as $detail) {
            $assignments[$detail->id] = $this->baseAssignment($detail);
            $ga = $latestGa->get($detail->inbound_order_id);

            if (!$ga) {
                $assignments[$detail->id]['notes'][] = 'Belum ada rekomendasi GA untuk inbound order ini.';
                continue;
            }

            $rows = $ga->details
                ->where('inbound_order_item_id', $detail->id)
                ->values();

            if ($rows->isEmpty()) {
                $assignments[$detail->id]['notes'][] = 'GA ada, tetapi tidak memiliki detail untuk item ini.';
                continue;
            }

            foreach ($rows as $row) {
                $cell = $row->cell ?: $cellsById->get($row->cell_id);
                if (!$cell) {
                    $assignments[$detail->id]['notes'][] = 'Detail GA menunjuk cell yang tidak ditemukan.';
                    continue;
                }

                $assignments[$detail->id]['placements'][] = array_merge(
                    $this->makePlacement($detail, $cell, (int) $row->quantity, 'ga_recommendation', $this->demandForCellSnapshot($detail->item, (int) $row->quantity, $cell, $cellSnapshot)),
                    [
                        'gene_fitness' => $row->gene_fitness !== null ? (float) $row->gene_fitness : null,
                        'fc_cap' => $row->fc_cap_score !== null ? (float) $row->fc_cap_score : null,
                        'fc_cat' => $row->fc_cat_score !== null ? (float) $row->fc_cat_score : null,
                        'fc_aff' => $row->fc_aff_score !== null ? (float) $row->fc_aff_score : null,
                        'fc_split' => $row->fc_split_score !== null ? (float) $row->fc_split_score : null,
                        'fc_mov' => $row->fc_mov_score !== null ? (float) $row->fc_mov_score : null,
                    ]
                );
            }

            $assignments[$detail->id]['ga_recommendation_id'] = $ga->id;
            $assignments[$detail->id]['ga_status'] = $ga->status;
            $assignments[$detail->id]['ga_fitness_score'] = $ga->fitness_score !== null ? (float) $ga->fitness_score : null;
        }

        return $assignments;
    }

    private function baseAssignment(InboundOrderItem $detail, array $notes = []): array
    {
        $item = $detail->item;

        return [
            'detail_id' => $detail->id,
            'order_id' => $detail->inbound_order_id,
            'do_number' => $detail->inboundOrder?->do_number,
            'warehouse_id' => $detail->inboundOrder?->warehouse_id,
            'item_id' => $item->id,
            'sku' => $item->sku,
            'item_name' => $item->name,
            'category' => $item->category?->name ?? '-',
            'category_id' => $item->category_id,
            'unit' => $item->unit?->code ?? '-',
            'qty' => (int) $detail->quantity_received,
            'movement_type' => $item->movement_type,
            'capacity_demand' => $this->capacityService->pointsForQuantity($item, (int) $detail->quantity_received),
            'placements' => [],
            'reference_cell_ids' => [],
            'notes' => $notes,
        ];
    }

    private function makePlacement(InboundOrderItem $detail, Cell $cell, int $qty, string $source, int $demand): array
    {
        return [
            'cell_id' => $cell->id,
            'cell_code' => $cell->physical_code,
            'cell_label' => $cell->physical_label,
            'quantity' => $qty,
            'demand_points' => max(0, $demand),
            'source' => $source,
        ];
    }

    private function demandForCellSnapshot($item, int $quantity, Cell $cell, array $cellSnapshot): int
    {
        $currentQty = (int) ($cellSnapshot[$cell->id]['item_quantities'][$item->id] ?? 0);
        $before = $currentQty > 0 ? $this->capacityService->pointsForQuantity($item, $currentQty) : 0;
        $after = $this->capacityService->pointsForQuantity($item, $currentQty + max(0, $quantity));

        return max(0, $after - $before);
    }

    private function scoreScenario(
        string $key,
        string $label,
        array $assignments,
        Collection $cellsById,
        array $cellSnapshot,
        Collection $existingStocks,
        array $affinityMap
    ): array {
        $cellDemand = [];
        $locationsByItem = [];

        foreach ($assignments as $assignment) {
            foreach ($assignment['reference_cell_ids'] ?? [] as $cellId) {
                $locationsByItem[$assignment['item_id']][] = (int) $cellId;
            }
            foreach ($assignment['placements'] as $placement) {
                $cellDemand[$placement['cell_id']] = ($cellDemand[$placement['cell_id']] ?? 0) + (int) $placement['demand_points'];
                $locationsByItem[$assignment['item_id']][] = (int) $placement['cell_id'];
            }
        }

        $fitnessValues = [];
        $detailDistances = [];
        $detailTimes = [];

        foreach ($assignments as $detailId => &$assignment) {
            $placementFitness = [];
            $placementDistances = [];
            $placementTimes = [];

            foreach ($assignment['placements'] as &$placement) {
                $cell = $cellsById->get($placement['cell_id']);
                $distanceFromOrigin = $this->distanceFromOrigin($cell);
                $placement['distance_from_origin'] = $distanceFromOrigin;
                $placement['estimated_time_seconds'] = self::BASE_TIME_PER_ITEM_SECONDS + ($distanceFromOrigin * self::TRAVEL_TIME_PER_DISTANCE_UNIT_SECONDS);

                $computed = $this->computePlacementFitness(
                    $assignment,
                    $placement,
                    $cell,
                    $cellDemand,
                    $cellSnapshot,
                    $locationsByItem,
                    $existingStocks,
                    $affinityMap,
                    $cellsById
                );

                $placement['computed_fitness'] = $computed['total'];
                $placement['computed_components'] = $computed['components'];

                $placementFitness[] = $placement['gene_fitness'] ?? $computed['total'];
                $placementDistances[] = $distanceFromOrigin;
                $placementTimes[] = $placement['estimated_time_seconds'];
            }
            unset($placement);

            $assignment['fitness'] = count($placementFitness) ? round(array_sum($placementFitness) / count($placementFitness), 4) : null;
            $assignment['distance_from_origin'] = count($placementDistances) ? round(array_sum($placementDistances) / count($placementDistances), 4) : null;
            $assignment['estimated_time_seconds'] = count($placementTimes) ? round(array_sum($placementTimes), 2) : null;
            $assignment['final_cell_codes'] = $this->cellCodes($this->finalLocationIds($assignment), $cellsById);
            $assignment['split_distance'] = $this->averagePairwiseDistance($this->finalLocationIds($assignment), $cellsById);

            if ($assignment['fitness'] !== null) {
                $fitnessValues[] = $assignment['fitness'];
            }
            if ($assignment['distance_from_origin'] !== null) {
                $detailDistances[] = $assignment['distance_from_origin'];
            }
            if ($assignment['estimated_time_seconds'] !== null) {
                $detailTimes[] = $assignment['estimated_time_seconds'];
            }
        }
        unset($assignment);

        $violations = 0;
        foreach ($cellDemand as $cellId => $demand) {
            if ($demand > ($cellSnapshot[$cellId]['capacity_remaining'] ?? 0)) {
                $violations++;
            }
        }

        $splitCount = 0;
        $totalLocations = 0;
        foreach ($locationsByItem as $cellIds) {
            $unique = collect($cellIds)->filter()->unique()->count();
            $totalLocations += $unique;
            if ($unique > 1) {
                $splitCount++;
            }
        }

        $skuCount = max(1, count($locationsByItem));

        return [
            'key' => $key,
            'label' => $label,
            'assignments' => $assignments,
            'metrics' => [
                'split_location_count' => $splitCount,
                'avg_locations_per_sku' => round($totalLocations / $skuCount, 4),
                'avg_placement_distance' => round($this->averageScenarioSplitDistance($locationsByItem, $cellsById), 4),
                'capacity_violation_count' => $violations,
                'estimated_putaway_time_seconds' => round(array_sum($detailTimes), 2),
                'avg_fitness_score' => count($fitnessValues) ? round(array_sum($fitnessValues) / count($fitnessValues), 4) : null,
            ],
        ];
    }

    private function computePlacementFitness(
        array $assignment,
        array $placement,
        ?Cell $cell,
        array $cellDemand,
        array $cellSnapshot,
        array $locationsByItem,
        Collection $existingStocks,
        array $affinityMap,
        Collection $cellsById
    ): array {
        if (!$cell) {
            return ['total' => 0.0, 'components' => ['cap' => 0, 'cat' => 0, 'aff' => 0, 'split' => 0, 'mov' => 0]];
        }

        $snap = $cellSnapshot[$cell->id] ?? ['capacity_remaining' => 0, 'existing_item_ids' => [], 'dominant_category_id' => null];
        $demand = $cellDemand[$cell->id] ?? (int) $placement['demand_points'];

        $fcCap = $demand <= $snap['capacity_remaining']
            ? self::FC_CAP
            : round(self::FC_CAP * max(0, $snap['capacity_remaining']) / max(1, $demand), 4);

        $hasSameSku = in_array((int) $assignment['item_id'], $snap['existing_item_ids'] ?? [], true);
        $cellCategory = $snap['dominant_category_id'] ?? null;
        if ($hasSameSku || ((int) $assignment['category_id'] > 0 && (int) $assignment['category_id'] === (int) $cellCategory)) {
            $fcCat = self::FC_CAT;
        } elseif ($cellCategory === null) {
            $fcCat = self::FC_CAT * 0.5;
        } else {
            $fcCat = 0.0;
        }

        $fcAff = $this->scoreAffinity($assignment, $cell, $existingStocks, $affinityMap, $cellsById);
        $fcSplit = $this->scoreSplit($assignment['item_id'], $cell->id, $locationsByItem, $cellsById);
        $fcMov = $this->scoreMovement($assignment['movement_type'] ?? null, $cell);

        return [
            'total' => round($fcCap + $fcCat + $fcAff + $fcSplit + $fcMov, 4),
            'components' => [
                'cap' => round($fcCap, 4),
                'cat' => round($fcCat, 4),
                'aff' => round($fcAff, 4),
                'split' => round($fcSplit, 4),
                'mov' => round($fcMov, 4),
            ],
        ];
    }

    private function scoreAffinity(array $assignment, Cell $cell, Collection $existingStocks, array $affinityMap, Collection $cellsById): float
    {
        $itemId = (int) $assignment['item_id'];
        $stocks = $existingStocks->get($itemId, collect());
        $existingCells = $stocks->pluck('cell')->filter()->unique('id')->values();

        if ($existingCells->pluck('id')->contains($cell->id)) {
            return self::FC_AFF;
        }

        $sameRack = $existingCells->contains(fn(Cell $existing) => $existing->rack_id === $cell->rack_id);
        if ($sameRack) {
            return 18.0;
        }

        $relatedScore = 0.0;
        foreach (($affinityMap[$itemId] ?? []) as $relatedItemId => $score) {
            $relatedStocks = $existingStocks->get($relatedItemId, collect());
            if ($relatedStocks->pluck('cell_id')->contains($cell->id)) {
                $relatedScore = max($relatedScore, (float) $score);
            }
        }
        if ($relatedScore > 0) {
            return min(self::FC_AFF, 10.0 + ($relatedScore * 10.0));
        }

        if ($existingCells->isEmpty()) {
            return 10.0;
        }

        $minDistance = $existingCells
            ->map(fn(Cell $existing) => $this->distanceBetweenCells($cell, $existing))
            ->min();

        if ($minDistance <= 1) {
            return 19.0;
        }
        if ($minDistance <= 5) {
            return 16.0;
        }
        if ($minDistance <= 10) {
            return 8.0;
        }

        return 0.0;
    }

    private function scoreSplit(int $itemId, int $cellId, array $locationsByItem, Collection $cellsById): float
    {
        $locations = collect($locationsByItem[$itemId] ?? [$cellId])->filter()->unique()->values();
        $locCount = $locations->count();

        if ($locCount <= 1) {
            return self::FC_SPLIT;
        }

        $countScore = 7.5 / $locCount;
        $current = $cellsById->get($cellId);
        $otherIds = $locations->reject(fn($id) => (int) $id === (int) $cellId);
        $minDistance = $otherIds
            ->map(fn($id) => $this->distanceBetweenCells($current, $cellsById->get($id)))
            ->min();

        if ($minDistance <= 1) {
            $distanceScore = 7.5;
        } elseif ($minDistance <= 5) {
            $distanceScore = 5.0;
        } elseif ($minDistance <= 10) {
            $distanceScore = 3.0;
        } else {
            $distanceScore = 0.0;
        }

        return round($countScore + $distanceScore, 4);
    }

    private function scoreMovement(?string $movementType, Cell $cell): float
    {
        if (!$movementType || $cell->blok === null) {
            return self::FC_MOV * 0.5;
        }

        $blok = (int) $cell->blok;

        return match ($movementType) {
            'fast_moving' => match (true) {
                $blok <= 1 => 10.0,
                $blok <= 2 => 8.0,
                $blok <= 3 => 5.0,
                default => 2.0,
            },
            'slow_moving' => match (true) {
                $blok >= 4 => 10.0,
                $blok >= 3 => 8.0,
                $blok >= 2 => 6.0,
                default => 3.0,
            },
            'non_moving' => match (true) {
                $blok >= 5 => 10.0,
                $blok >= 4 => 7.0,
                $blok >= 3 => 4.0,
                default => 1.0,
            },
            default => self::FC_MOV * 0.5,
        };
    }

    private function nearestAvailableCell(Collection $cells, ?Cell $anchor, InboundOrderItem $detail, array $cellSnapshot): ?Cell
    {
        $item = $detail->item;
        $qty = (int) $detail->quantity_received;

        return $cells
            ->filter(fn(Cell $cell) => (int) $cell->rack?->warehouse_id === (int) $detail->inboundOrder?->warehouse_id)
            ->sortBy(function (Cell $cell) use ($anchor, $item, $qty, $cellSnapshot) {
                $demand = $this->demandForCellSnapshot($item, $qty, $cell, $cellSnapshot);
                $capacityPenalty = (($cellSnapshot[$cell->id]['capacity_remaining'] ?? 0) >= $demand) ? 0 : 10000;
                $categoryPenalty = ((int) ($cellSnapshot[$cell->id]['dominant_category_id'] ?? 0) === (int) $item->category_id) ? 0 : 100;
                $distance = $anchor ? $this->distanceBetweenCells($anchor, $cell) : $this->distanceFromOrigin($cell);
                return $capacityPenalty + $categoryPenalty + $distance;
            })
            ->first();
    }

    private function finalLocationIds(array $assignment): array
    {
        return collect($assignment['reference_cell_ids'] ?? [])
            ->merge(collect($assignment['placements'])->pluck('cell_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function cellCodes(array $cellIds, Collection $cellsById): string
    {
        return collect($cellIds)
            ->map(fn($id) => $cellsById->get($id)?->physical_code)
            ->filter()
            ->unique()
            ->implode(', ') ?: '-';
    }

    private function averageScenarioSplitDistance(array $locationsByItem, Collection $cellsById): float
    {
        $values = [];
        foreach ($locationsByItem as $cellIds) {
            $values[] = $this->averagePairwiseDistance($cellIds, $cellsById);
        }

        return count($values) ? array_sum($values) / count($values) : 0.0;
    }

    private function averagePairwiseDistance(array $cellIds, Collection $cellsById): float
    {
        $ids = collect($cellIds)->filter()->unique()->values();
        if ($ids->count() <= 1) {
            return 0.0;
        }

        $sum = 0.0;
        $pairs = 0;
        for ($i = 0; $i < $ids->count(); $i++) {
            for ($j = $i + 1; $j < $ids->count(); $j++) {
                $sum += $this->distanceBetweenCells($cellsById->get($ids[$i]), $cellsById->get($ids[$j]));
                $pairs++;
            }
        }

        return $pairs > 0 ? round($sum / $pairs, 4) : 0.0;
    }

    private function distanceFromOrigin(?Cell $cell): float
    {
        if (!$cell) {
            return 0.0;
        }

        $origin = new Cell([
            'blok' => 1,
            'grup' => 'A',
            'kolom' => 1,
            'baris' => 1,
            'rack_id' => 0,
            'column' => 1,
            'level' => 1,
        ]);

        return $this->distanceBetweenCells($origin, $cell);
    }

    private function groupIndex(string|int|null $group): int
    {
        if ($group === null || $group === '') {
            return 1;
        }

        if (is_numeric($group)) {
            return (int) $group;
        }

        return max(1, ord(strtoupper((string) $group)[0]) - ord('A') + 1);
    }

    private function buildSummaryRows(array $scenarios): array
    {
        $metrics = [
            'split_location_count' => ['label' => 'Split Location Count', 'lower' => true, 'format' => 'int', 'interpretation' => 'Jumlah SKU yang tersebar di lebih dari satu cell. Lebih kecil berarti lokasi lebih terkonsolidasi.'],
            'avg_locations_per_sku' => ['label' => 'Average Location per SKU', 'lower' => true, 'format' => 'decimal', 'interpretation' => 'Rata-rata jumlah lokasi unik per SKU. Nilai mendekati 1 lebih baik.'],
            'avg_placement_distance' => ['label' => 'Average Placement Distance', 'lower' => true, 'format' => 'decimal', 'interpretation' => 'Rata-rata jarak antar lokasi untuk SKU yang sama. Lebih kecil berarti split lebih dekat.'],
            'capacity_violation_count' => ['label' => 'Capacity Violation Count', 'lower' => true, 'format' => 'int', 'interpretation' => 'Jumlah cell yang demand tambahannya melebihi sisa kapasitas. Lebih kecil lebih baik.'],
            'estimated_putaway_time_seconds' => ['label' => 'Estimated Put-away Time', 'lower' => true, 'format' => 'time', 'interpretation' => 'Estimasi waktu dari titik awal gudang ke lokasi penempatan. Lebih kecil berarti rute lebih efisien.'],
            'avg_fitness_score' => ['label' => 'Average Fitness Score', 'lower' => false, 'format' => 'decimal', 'interpretation' => 'Skor gabungan FC_CAP, FC_CAT, FC_AFF, FC_SPLIT, dan FC_MOV. Lebih tinggi lebih baik.'],
        ];

        $rows = [];
        foreach ($metrics as $key => $meta) {
            $existing = $scenarios['existing']['metrics'][$key] ?? null;
            $random = $scenarios['random']['metrics'][$key] ?? null;
            $ga = $scenarios['ga']['metrics'][$key] ?? null;

            $rows[] = [
                'key' => $key,
                'label' => $meta['label'],
                'existing' => $this->formatMetric($existing, $meta['format']),
                'random' => $this->formatMetric($random, $meta['format']),
                'ga' => $this->formatMetric($ga, $meta['format']),
                'improvement_existing' => $this->calculateImprovement($existing, $ga, $meta['lower']),
                'improvement_random' => $this->calculateImprovement($random, $ga, $meta['lower']),
                'interpretation' => $meta['interpretation'],
            ];
        }

        return $rows;
    }

    private function buildDetailRows(Collection $details, array $scenarios): array
    {
        return $details->map(function (InboundOrderItem $detail) use ($scenarios) {
            $existing = $scenarios['existing']['assignments'][$detail->id] ?? null;
            $random = $scenarios['random']['assignments'][$detail->id] ?? null;
            $ga = $scenarios['ga']['assignments'][$detail->id] ?? null;

            return [
                'do_number' => $detail->inboundOrder?->do_number,
                'sku' => $detail->item?->sku,
                'item_name' => $detail->item?->name,
                'category' => $detail->item?->category?->name ?? '-',
                'qty' => (int) $detail->quantity_received,
                'unit' => $detail->item?->unit?->code ?? '-',
                'movement_type' => $detail->item?->movement_type ?? '-',
                'capacity_demand' => $this->capacityService->pointsForQuantity($detail->item, (int) $detail->quantity_received),
                'existing_cell' => $existing['final_cell_codes'] ?? '-',
                'random_cell' => $random['final_cell_codes'] ?? '-',
                'ga_cell' => $ga['final_cell_codes'] ?? '-',
                'distance_existing' => $existing['distance_from_origin'] ?? null,
                'distance_random' => $random['distance_from_origin'] ?? null,
                'distance_ga' => $ga['distance_from_origin'] ?? null,
                'fitness_existing' => $existing['fitness'] ?? null,
                'fitness_random' => $random['fitness'] ?? null,
                'fitness_ga' => $ga['fitness'] ?? null,
                'notes' => collect($existing['notes'] ?? [])
                    ->merge($random['notes'] ?? [])
                    ->merge($ga['notes'] ?? [])
                    ->unique()
                    ->implode(' '),
            ];
        })->values()->all();
    }

    private function collectNotes(array $scenarios): array
    {
        $notes = [];
        foreach ($scenarios as $scenario) {
            foreach ($scenario['assignments'] as $assignment) {
                foreach ($assignment['notes'] ?? [] as $note) {
                    $notes[] = $assignment['do_number'] . ' - ' . $assignment['sku'] . ': ' . $note;
                }
            }
        }

        return collect($notes)->unique()->values()->all();
    }

    private function buildNarrative(array $scenarios): string
    {
        $rows = $this->buildSummaryRows($scenarios);
        $betterExisting = collect($rows)
            ->filter(fn($row) => ($row['improvement_existing']['value'] ?? -1) > 0)
            ->pluck('label')
            ->values()
            ->all();

        $betterRandom = collect($rows)
            ->filter(fn($row) => ($row['improvement_random']['value'] ?? -1) > 0)
            ->pluck('label')
            ->values()
            ->all();

        $parts = [];
        if ($betterExisting) {
            $parts[] = 'dibandingkan kondisi existing pada metrik ' . implode(', ', $betterExisting);
        }
        if ($betterRandom) {
            $parts[] = 'dibandingkan random placement pada metrik ' . implode(', ', $betterRandom);
        }

        if (!$parts) {
            return 'Berdasarkan scope inbound order yang dipilih, rekomendasi GA belum menunjukkan perbaikan dominan terhadap baseline existing maupun random. Hasil ini perlu dibaca sesuai data uji, kondisi kapasitas cell, dan rekomendasi GA yang tersedia pada saat evaluasi dijalankan.';
        }

        return 'Berdasarkan scope inbound order yang dipilih, rekomendasi Genetic Algorithm menunjukkan perbaikan ' . implode(' serta ', $parts) . '. Hasil ini menunjukkan bahwa rekomendasi GA dapat membantu pemilihan lokasi penyimpanan secara lebih terukur berdasarkan kapasitas, kategori, afinitas, split location, dan movement type, tanpa mengabaikan metrik yang belum mengalami perbaikan.';
    }

    private function formatMetric(float|int|null $value, string $format): string
    {
        if ($value === null) {
            return '-';
        }

        return match ($format) {
            'int' => number_format((int) round($value)),
            'time' => $this->formatSeconds((float) $value),
            default => number_format((float) $value, 4),
        };
    }

    private function formatSeconds(float $seconds): string
    {
        if ($seconds >= 3600) {
            return number_format($seconds / 3600, 2) . ' jam';
        }

        if ($seconds >= 60) {
            return number_format($seconds / 60, 2) . ' menit';
        }

        return number_format($seconds, 0) . ' detik';
    }
}
