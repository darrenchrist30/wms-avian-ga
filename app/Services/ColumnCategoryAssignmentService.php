<?php

namespace App\Services;

use App\Models\Cell;
use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ColumnCategoryAssignmentService
{
    public const DEFAULT_THRESHOLD = 80.0;

    public function preview($blok, $grup, $kolom, float $threshold = self::DEFAULT_THRESHOLD): array
    {
        $blok = (int) $blok;
        $grup = strtoupper(trim((string) $grup));
        $kolom = (int) $kolom;
        $threshold = max(0, min(100, $threshold));

        if (!Schema::hasTable('mspart')) {
            return $this->emptyPreview($blok, $grup, $kolom, $threshold, 'Tabel mspart tidak ditemukan.');
        }

        $rows = DB::table('mspart')
            ->where('isdel', 0)
            ->where('blok', (string) $blok)
            ->where('grup', $grup)
            ->where('kolom', (string) $kolom)
            ->whereNotNull('kode')
            ->select(['kode', 'nama', 'stok', 'sat', 'blok', 'grup', 'kolom', 'baris'])
            ->orderByRaw('CAST(baris AS UNSIGNED)')
            ->orderBy('kode')
            ->get();

        if ($rows->isEmpty()) {
            return $this->emptyPreview($blok, $grup, $kolom, $threshold, 'Tidak ada data real mspart pada kolom ini.');
        }

        $uniqueSkuRows = $rows
            ->groupBy(fn($row) => $this->normalizeSku($row->kode))
            ->map(fn(Collection $group) => $group->first())
            ->values();

        $itemBySku = $this->itemMapForSkus(
            $uniqueSkuRows->map(fn($row) => $this->normalizeSku($row->kode))->values()
        );

        $categoryCounts = [];
        $allSamples = [];
        $unmappedSkus = [];

        foreach ($uniqueSkuRows as $row) {
            $sku = $this->normalizeSku($row->kode);
            $item = $itemBySku->get($sku);
            $categoryId = $item?->category_id;

            if ($categoryId) {
                $categoryCounts[$categoryId] = ($categoryCounts[$categoryId] ?? 0) + 1;
            } else {
                $unmappedSkus[] = (string) $row->kode;
            }

            $allSamples[] = [
                'sku' => (string) $row->kode,
                'name' => (string) ($item?->name ?: $row->nama),
                'category' => (string) ($item?->category?->name ?: 'Tanpa kategori'),
                'baris' => $row->baris,
                'stock' => $row->stok,
                'unit' => $row->sat,
            ];
        }

        arsort($categoryCounts);
        $dominantCategoryId = array_key_first($categoryCounts);
        $dominantCount = $dominantCategoryId ? (int) $categoryCounts[$dominantCategoryId] : 0;
        $uniqueSkuCount = $uniqueSkuRows->count();
        $dominancePercent = $uniqueSkuCount > 0 ? round($dominantCount / $uniqueSkuCount * 100, 2) : 0.0;
        $categoryById = ItemCategory::whereIn('id', array_keys($categoryCounts))->get(['id', 'name'])->keyBy('id');
        $dominantCategory = $dominantCategoryId ? $categoryById->get($dominantCategoryId) : null;

        $cellSummary = $this->cellSummary($blok, $grup, $kolom);
        $stockPreview = $this->stockPreview($blok, $grup, $kolom);
        $categoryBreakdown = collect($categoryCounts)
            ->map(function ($count, $categoryId) use ($uniqueSkuCount, $categoryById) {
                $category = $categoryById->get($categoryId);

                return [
                    'category_id' => (int) $categoryId,
                    'category' => $category?->name ?: 'Tanpa kategori',
                    'count' => (int) $count,
                    'percent' => $uniqueSkuCount > 0 ? round(((int) $count / $uniqueSkuCount) * 100, 2) : 0.0,
                ];
            })
            ->values()
            ->all();

        // Show every unique SKU found in the real MSpart column so category review is auditable.
        $samples = collect($allSamples)
            ->sortBy([
                ['category', 'asc'],
                ['baris', 'asc'],
                ['sku', 'asc'],
            ])
            ->values()
            ->all();

        $coordinateIsValid = $blok > 0 && $kolom > 0 && preg_match('/^[A-Z]$/', $grup);
        $isReady = $coordinateIsValid
            && $dominantCategory
            && $uniqueSkuCount > 0
            && $dominancePercent >= $threshold;

        $notes = [];
        if (!$coordinateIsValid) {
            $notes[] = 'Koordinat kolom tidak normal, perlu dicek manual.';
        }
        if (!$dominantCategory) {
            $notes[] = 'Tidak ada kategori dominan yang bisa dipetakan dari master item.';
        }
        if ($dominancePercent < $threshold) {
            $notes[] = 'Dominansi kategori di bawah threshold ' . rtrim(rtrim(number_format($threshold, 2), '0'), '.') . '%.';
        }
        if (!empty($unmappedSkus)) {
            $notes[] = count($unmappedSkus) . ' SKU belum cocok ke master item/kategori.';
        }

        $isAlreadyApplied = $isReady
            && (int) ($cellSummary['neutral_cells'] ?? 0) === 0
            && (int) ($cellSummary['categorized_cells'] ?? 0) > 0;

        if ($isAlreadyApplied) {
            $notes[] = 'Semua cell aktif di kolom ini sudah memiliki kategori. Mode aman tidak perlu apply ulang.';
        }

        return [
            'status' => $isAlreadyApplied ? 'applied' : ($isReady ? 'ready' : 'review'),
            'status_label' => $isAlreadyApplied ? 'Sudah Apply' : ($isReady ? 'Siap Apply' : 'Perlu Review'),
            'can_apply' => $isReady && !$isAlreadyApplied,
            'can_overwrite' => $isReady,
            'location' => [
                'blok' => $blok,
                'grup' => $grup,
                'kolom' => $kolom,
                'code' => "{$blok}-{$grup}-{$kolom}",
            ],
            'threshold' => $threshold,
            'total_rows' => $rows->count(),
            'unique_sku_count' => $uniqueSkuCount,
            'dominant_category_id' => $dominantCategory?->id,
            'dominant_category' => $dominantCategory?->name,
            'dominant_count' => $dominantCount,
            'dominance_percent' => $dominancePercent,
            'category_breakdown' => $categoryBreakdown,
            'unmapped_skus' => array_slice($unmappedSkus, 0, 12),
            'samples' => $samples,
            'stock_samples' => $stockPreview['samples'],
            'stock_summary' => $stockPreview['summary'],
            'cell_summary' => $cellSummary,
            'notes' => $notes,
        ];
    }

    public function apply($blok, $grup, $kolom, int $categoryId, string $mode = 'neutral_only', float $threshold = self::DEFAULT_THRESHOLD): array
    {
        $preview = $this->preview($blok, $grup, $kolom, $threshold);

        if (!in_array($preview['status'], ['ready', 'applied'], true)) {
            throw ValidationException::withMessages([
                'kolom' => 'Kolom masih Perlu Review. Periksa dominansi kategori sebelum apply.',
            ]);
        }

        if ((int) $preview['dominant_category_id'] !== $categoryId) {
            throw ValidationException::withMessages([
                'dominant_category_id' => 'Kategori yang dipilih tidak sama dengan kategori dominan hasil preview.',
            ]);
        }

        $mode = $mode === 'overwrite' ? 'overwrite' : 'neutral_only';
        $query = Cell::where('blok', (int) $blok)
            ->whereRaw('UPPER(grup) = ?', [strtoupper(trim((string) $grup))])
            ->where('kolom', (int) $kolom)
            ->whereNotNull('baris')
            ->where('is_active', true);

        if ($mode === 'neutral_only') {
            $query->whereNull('dominant_category_id');
        }

        $cells = $query->get();
        $updated = 0;

        DB::transaction(function () use ($cells, $categoryId, &$updated) {
            foreach ($cells as $cell) {
                $cell->dominant_category_id = $categoryId;
                $cell->save();
                $updated++;
            }
        });

        return [
            'updated_count' => $updated,
            'mode' => $mode,
            'category' => $preview['dominant_category'],
            'location' => $preview['location'],
            'cell_summary' => $this->cellSummary((int) $blok, strtoupper(trim((string) $grup)), (int) $kolom),
        ];
    }

    private function emptyPreview(int $blok, string $grup, int $kolom, float $threshold, string $message): array
    {
        return [
            'status' => 'review',
            'status_label' => 'Perlu Review',
            'location' => [
                'blok' => $blok,
                'grup' => $grup,
                'kolom' => $kolom,
                'code' => "{$blok}-{$grup}-{$kolom}",
            ],
            'threshold' => $threshold,
            'total_rows' => 0,
            'unique_sku_count' => 0,
            'dominant_category_id' => null,
            'dominant_category' => null,
            'dominant_count' => 0,
            'dominance_percent' => 0.0,
            'category_breakdown' => [],
            'unmapped_skus' => [],
            'samples' => [],
            'stock_samples' => [],
            'stock_summary' => [
                'stock_records' => 0,
                'unique_sku_count' => 0,
                'total_quantity' => 0,
            ],
            'cell_summary' => $this->cellSummary($blok, $grup, $kolom),
            'notes' => [$message],
        ];
    }

    private function itemMapForSkus(Collection $skus): Collection
    {
        $skus = $skus
            ->map(fn($sku) => $this->normalizeSku($sku))
            ->filter()
            ->unique()
            ->values();

        if ($skus->isEmpty()) {
            return collect();
        }

        return Item::with('category')
            ->whereIn('sku', $skus->all())
            ->get(['id', 'sku', 'name', 'category_id'])
            ->keyBy(fn(Item $item) => $this->normalizeSku($item->sku));
    }

    private function cellSummary(int $blok, string $grup, int $kolom): array
    {
        $cells = Cell::with('dominantCategory')
            ->where('blok', $blok)
            ->whereRaw('UPPER(grup) = ?', [strtoupper($grup)])
            ->where('kolom', $kolom)
            ->whereNotNull('baris')
            ->where('is_active', true)
            ->get();

        $categorySummary = $cells
            ->filter(fn(Cell $cell) => $cell->dominant_category_id !== null)
            ->groupBy('dominant_category_id')
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'category_id' => (int) $first->dominant_category_id,
                    'category' => $first->dominantCategory?->name ?: 'Tanpa kategori',
                    'count' => $group->count(),
                ];
            })
            ->values()
            ->all();

        return [
            'total_cells' => $cells->count(),
            'neutral_cells' => $cells->filter(fn(Cell $cell) => $cell->dominant_category_id === null)->count(),
            'categorized_cells' => $cells->filter(fn(Cell $cell) => $cell->dominant_category_id !== null)->count(),
            'category_summary' => $categorySummary,
        ];
    }

    private function stockPreview(int $blok, string $grup, int $kolom): array
    {
        $rows = DB::table('stock_records as sr')
            ->join('items as i', 'i.id', '=', 'sr.item_id')
            ->leftJoin('item_categories as ic', 'ic.id', '=', 'i.category_id')
            ->join('cells as c', 'c.id', '=', 'sr.cell_id')
            ->where('sr.quantity', '>', 0)
            ->whereIn('sr.status', ['available', 'reserved'])
            ->where('c.blok', $blok)
            ->whereRaw('UPPER(c.grup) = ?', [strtoupper($grup)])
            ->where('c.kolom', $kolom)
            ->select([
                'i.sku',
                'i.name',
                'ic.name as category',
                'sr.quantity',
                'sr.status',
                'c.code as cell_code',
                'c.baris',
            ])
            ->orderByRaw('CAST(c.baris AS UNSIGNED)')
            ->orderBy('i.sku')
            ->get();

        $grouped = $rows
            ->groupBy(fn($row) => $this->normalizeSku($row->sku) . '|' . $row->cell_code)
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'sku' => (string) $first->sku,
                    'name' => (string) $first->name,
                    'category' => (string) ($first->category ?: 'Tanpa kategori'),
                    'baris' => $first->baris,
                    'cell' => (string) $first->cell_code,
                    'quantity' => (float) $group->sum('quantity'),
                    'status' => $group->pluck('status')->unique()->implode(', '),
                ];
            })
            ->values();

        return [
            'summary' => [
                'stock_records' => $rows->count(),
                'unique_sku_count' => $rows->pluck('sku')->map(fn($sku) => $this->normalizeSku($sku))->unique()->count(),
                'total_quantity' => (float) $rows->sum('quantity'),
            ],
            'samples' => $grouped->take(12)->all(),
        ];
    }

    private function normalizeSku($sku): string
    {
        return strtoupper(trim((string) $sku));
    }
}
