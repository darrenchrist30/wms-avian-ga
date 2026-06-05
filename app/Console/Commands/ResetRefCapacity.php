<?php

namespace App\Console\Commands;

use App\Models\Cell;
use App\Services\CellCapacityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetRefCapacity extends Command
{
    protected $signature = 'refdata:reset-capacity';

    protected $description = 'Reset cells.capacity_used dari total quantity stock_records aktif. Aman, tidak ubah items/stock_records.';

    public function handle(): int
    {
        $this->info('Reset capacity_used dari total quantity stock_records aktif...');

        DB::table('cells')->update(['capacity_used' => 0, 'status' => 'available']);

        $cellIds = DB::table('stock_records')
            ->where('status', 'available')
            ->where('quantity', '>', 0)
            ->distinct()
            ->pluck('cell_id');

        if ($cellIds->isEmpty()) {
            $this->info('Tidak ada stock_records aktif. Semua cell di-reset ke 0.');
            return self::SUCCESS;
        }

        $capService = app(CellCapacityService::class);
        $updated = 0;
        $bar = $this->output->createProgressBar($cellIds->count());
        $bar->start();

        foreach ($cellIds as $cellId) {
            $cell = Cell::find($cellId);
            if (!$cell) {
                $bar->advance();
                continue;
            }

            // refresh() menghitung total quantity aktif, simpan ke capacity_used, lalu update status.
            $capService->refresh($cell);
            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("{$updated} cells di-reset dengan benar dari stock_records.");

        return self::SUCCESS;
    }
}
