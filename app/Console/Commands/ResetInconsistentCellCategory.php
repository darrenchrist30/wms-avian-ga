<?php

namespace App\Console\Commands;

use App\Models\Cell;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetInconsistentCellCategory extends Command
{
    protected $signature = 'cells:reset-inconsistent-category
                            {--apply : Simpan reset ke database. Tanpa opsi ini hanya preview}
                            {--warehouse= : ID gudang tertentu (default: semua)}';

    protected $description = 'Reset dominant_category_id pada kolom cell yang parsial atau campur kategori';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $warehouseId = $this->option('warehouse');

        $cells = Cell::with(['dominantCategory', 'rack'])
            ->where('is_active', true)
            ->whereNotNull('blok')
            ->whereNotNull('grup')
            ->whereNotNull('kolom')
            ->whereNotNull('baris')
            ->when($warehouseId, fn($q) => $q->whereHas('rack', fn($r) => $r->where('warehouse_id', $warehouseId)))
            ->orderBy('blok')
            ->orderBy('grup')
            ->orderBy('kolom')
            ->orderBy('baris')
            ->get();

        $problemColumns = $cells
            ->groupBy(fn(Cell $cell) => $this->columnKey($cell))
            ->map(function ($columnCells, string $columnCode) {
                $categorized = $columnCells->filter(fn(Cell $cell) => $cell->dominant_category_id !== null);

                if ($categorized->isEmpty()) {
                    return null;
                }

                $categoryGroups = $categorized
                    ->groupBy('dominant_category_id')
                    ->map(function ($group) {
                        $first = $group->first();

                        return [
                            'category' => $first->dominantCategory?->name ?: 'Tanpa kategori',
                            'count' => $group->count(),
                            'cells' => $group->pluck('physical_code')->sort()->values()->all(),
                        ];
                    })
                    ->values();

                $isPartial = $categorized->count() < $columnCells->count();
                $isMixed = $categoryGroups->count() > 1;

                if (!$isPartial && !$isMixed) {
                    return null;
                }

                return [
                    'column' => $columnCode,
                    'total_cells' => $columnCells->count(),
                    'categorized_cells' => $categorized->count(),
                    'neutral_cells' => $columnCells->count() - $categorized->count(),
                    'category_groups' => $categoryGroups->all(),
                    'cell_ids' => $categorized->pluck('id')->all(),
                ];
            })
            ->filter()
            ->values();

        if ($problemColumns->isEmpty()) {
            $this->info('Tidak ada kolom parsial/campur yang perlu direset.');
            return self::SUCCESS;
        }

        $this->info(($apply ? '[APPLY] ' : '[PREVIEW] ') . 'Kolom parsial/campur yang terdeteksi:');
        $this->table(
            ['Kolom', 'Total Cell', 'Berkategori', 'Netral', 'Kategori Saat Ini'],
            $problemColumns->map(function ($row) {
                $categories = collect($row['category_groups'])
                    ->map(fn($group) => "{$group['category']} ({$group['count']})")
                    ->implode('; ');

                return [
                    $row['column'],
                    $row['total_cells'],
                    $row['categorized_cells'],
                    $row['neutral_cells'],
                    $categories,
                ];
            })->all()
        );

        $cellIds = $problemColumns->pluck('cell_ids')->flatten()->unique()->values();

        if (!$apply) {
            $this->warn("Preview saja. {$cellIds->count()} cell akan direset jika menjalankan dengan --apply.");
            $this->line('Contoh: php artisan cells:reset-inconsistent-category --apply');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($cellIds) {
            Cell::whereIn('id', $cellIds)->update(['dominant_category_id' => null]);
        });

        $this->info("Selesai. {$cellIds->count()} cell pada {$problemColumns->count()} kolom direset menjadi netral.");

        return self::SUCCESS;
    }

    private function columnKey(Cell $cell): string
    {
        return sprintf('%s-%s-%s', $cell->blok, strtoupper((string) $cell->grup), $cell->kolom);
    }
}
