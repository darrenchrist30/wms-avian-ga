<?php

namespace App\Console\Commands;

use App\Models\Cell;
use App\Services\CellCapacityService;
use Illuminate\Console\Command;

class SyncCellCapacity extends Command
{
    protected $signature = 'cells:sync-capacity
                            {--warehouse= : ID gudang tertentu (default: semua)}
                            {--dry-run    : Tampilkan perubahan tanpa menyimpan}';

    protected $description = 'Sync ulang capacity_used dan status semua cell dari data stok nyata';

    public function handle(CellCapacityService $svc): int
    {
        $warehouseId = $this->option('warehouse');
        $dryRun      = $this->option('dry-run');

        $query = Cell::with('rack')
            ->where('is_active', true)
            ->when($warehouseId, fn($q) => $q->whereHas('rack', fn($r) => $r->where('warehouse_id', $warehouseId)));

        $total   = $query->count();
        $updated = 0;
        $skipped = 0;

        $this->info($dryRun ? "[DRY RUN] " : "" . "Sync {$total} cell aktif...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunk(200, function ($cells) use ($svc, $dryRun, &$updated, &$skipped, $bar) {
            foreach ($cells as $cell) {
                $oldUsed   = (int) $cell->capacity_used;
                $oldStatus = $cell->status;

                $newUsed   = $svc->usedPoints($cell);
                $capMax    = $svc->capacityMax($cell);
                $newStatus = $newUsed <= 0
                    ? 'available'
                    : ($newUsed >= $capMax ? 'full' : 'partial');

                if ($oldUsed !== $newUsed || $oldStatus !== $newStatus) {
                    if (!$dryRun) {
                        $svc->refresh($cell);
                    }
                    $updated++;
                } else {
                    $skipped++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['', 'Jumlah'],
            [
                ['Total cell', $total],
                ['Diperbarui', $updated],
                ['Sudah sinkron', $skipped],
            ]
        );

        if ($dryRun && $updated > 0) {
            $this->warn("{$updated} cell perlu diperbarui. Jalankan tanpa --dry-run untuk menyimpan.");
        } elseif (!$dryRun) {
            $this->info("Selesai. {$updated} cell diperbarui.");
        }

        return self::SUCCESS;
    }
}
