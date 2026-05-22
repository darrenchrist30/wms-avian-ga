<?php

namespace App\Console\Commands;

use App\Models\Cell;
use App\Services\CellCapacityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetRefCapacity extends Command
{
    protected $signature   = 'refdata:reset-capacity';
    protected $description = 'Reset cells.capacity_used ke kondisi asli (hitung dari stock_records pakai formula CellCapacityService). Aman, tidak ubah items/stock_records.';

    public function handle(): int
    {
        $this->info('Reset capacity_used dari stock_records (formula: ceil(qty × 100 / item.max_stock))...');

        // Reset semua ke 0 + available
        DB::table('cells')->update(['capacity_used' => 0, 'status' => 'available']);

        // Kumpulkan cell_id yang punya stok aktif
        $cellIds = DB::table('stock_records')
            ->where('status', 'available')
            ->where('quantity', '>', 0)
            ->distinct()
            ->pluck('cell_id');

        if ($cellIds->isEmpty()) {
            $this->info('Tidak ada stock_records aktif. Semua cell di-reset ke 0.');
            return Command::SUCCESS;
        }

        $capService = app(CellCapacityService::class);
        $updated    = 0;
        $bar        = $this->output->createProgressBar($cellIds->count());
        $bar->start();

        foreach ($cellIds as $cellId) {
            $cell = Cell::find($cellId);
            if (!$cell) { $bar->advance(); continue; }

            // refresh() = hitung usedPoints (normalisasi per item) → simpan ke capacity_used → update status
            $capService->refresh($cell);
            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ {$updated} cells di-reset dengan benar dari stock_records.");
        return Command::SUCCESS;
    }
}
