<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetRefCapacity extends Command
{
    protected $signature   = 'refdata:reset-capacity';
    protected $description = 'Reset cells.capacity_used ke kondisi asli (hitung dari stock_records). Aman, tidak ubah items/stock_records.';

    public function handle(): int
    {
        $this->info('Reset capacity_used dari stock_records yang ada...');

        // Reset semua ke 0 dulu
        DB::table('cells')->update(['capacity_used' => 0, 'status' => 'available']);

        // Hitung ulang dari stock_records yang memang ada di WMS
        $rows = DB::select("
            SELECT cell_id, SUM(quantity) as total
            FROM stock_records
            WHERE status = 'available'
            GROUP BY cell_id
        ");

        $updated = 0;
        foreach ($rows as $row) {
            $capMax = DB::table('cells')->where('id', $row->cell_id)->value('capacity_max') ?: 20;
            $status = $row->total >= $capMax ? 'full' : ($row->total > 0 ? 'partial' : 'available');
            DB::table('cells')->where('id', $row->cell_id)->update([
                'capacity_used' => $row->total,
                'status'        => $status,
            ]);
            $updated++;
        }

        $this->info("✅ {$updated} cells di-reset dari stock_records.");
        return Command::SUCCESS;
    }
}
