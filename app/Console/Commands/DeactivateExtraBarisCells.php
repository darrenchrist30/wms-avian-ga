<?php

namespace App\Console\Commands;

use App\Models\Cell;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeactivateExtraBarisCells extends Command
{
    protected $signature = 'cells:deactivate-extra-baris
                            {--max= : Maksimal baris aktif. Default mengikuti config warehouse.max_active_baris}
                            {--warehouse= : ID gudang tertentu. Default semua gudang}
                            {--apply : Simpan perubahan. Tanpa opsi ini hanya preview}';

    protected $description = 'Preview/apply nonaktifkan cell MSpart dengan baris di atas batas operasional';

    public function handle(): int
    {
        $maxBaris = $this->option('max') !== null
            ? max(1, (int) $this->option('max'))
            : Cell::operationalBarisMax();
        $warehouseId = $this->option('warehouse');
        $apply = (bool) $this->option('apply');

        $query = Cell::with('rack')
            ->where('is_active', true)
            ->whereNotNull('blok')
            ->whereNotNull('grup')
            ->whereNotNull('kolom')
            ->whereNotNull('baris')
            ->where('baris', '>', $maxBaris)
            ->when($warehouseId, fn($q) => $q->whereHas('rack', fn($r) => $r->where('warehouse_id', $warehouseId)))
            ->orderBy('blok')
            ->orderBy('grup')
            ->orderBy('kolom')
            ->orderBy('baris');

        $cells = $query->get();

        if ($cells->isEmpty()) {
            $this->info("Tidak ada cell aktif dengan baris > {$maxBaris}.");
            return self::SUCCESS;
        }

        $columns = $cells
            ->groupBy(fn(Cell $cell) => sprintf('%s-%s-%s', $cell->blok, strtoupper((string) $cell->grup), $cell->kolom))
            ->map(function ($columnCells, string $column) {
                return [
                    'column' => $column,
                    'count' => $columnCells->count(),
                    'baris' => $columnCells->pluck('baris')->unique()->sort()->implode(', '),
                    'sample' => $columnCells->take(5)->map(fn(Cell $cell) => $cell->physical_code)->implode(', '),
                ];
            })
            ->values();

        $this->info(($apply ? '[APPLY] ' : '[PREVIEW] ') . "Cell aktif dengan baris > {$maxBaris}:");
        $this->table(
            ['Kolom', 'Jumlah Cell', 'Baris Terdeteksi', 'Contoh Cell'],
            $columns->map(fn($row) => [$row['column'], $row['count'], $row['baris'], $row['sample']])->all()
        );

        if (!$apply) {
            $this->warn("Preview saja. {$cells->count()} cell akan diubah menjadi inactive + blocked jika menjalankan dengan --apply.");
            $this->line("Contoh: php artisan cells:deactivate-extra-baris --max={$maxBaris} --apply");
            return self::SUCCESS;
        }

        DB::transaction(function () use ($cells) {
            Cell::whereIn('id', $cells->pluck('id'))->update([
                'is_active' => false,
                'status' => 'blocked',
            ]);
        });

        $this->info("Selesai. {$cells->count()} cell pada {$columns->count()} kolom dinonaktifkan.");

        return self::SUCCESS;
    }
}
