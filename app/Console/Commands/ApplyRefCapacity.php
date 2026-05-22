<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApplyRefCapacity extends Command
{
    protected $signature   = 'refdata:apply-capacity';
    protected $description = 'Update cells.capacity_used dari cells_ref. items & stock_records tidak disentuh.';

    public function handle(): int
    {
        if (!Schema::hasTable('cells_ref')) {
            $this->error('Tabel cells_ref belum ada. Jalankan: php artisan import:ref-data && php artisan refdata:enrich');
            return Command::FAILURE;
        }

        $refCount = DB::table('cells_ref')->where('stok', '>', 0)->count();
        $this->info("cells_ref: {$refCount} baris dengan stok > 0");

        // Reset dulu semua capacity ke 0
        DB::table('cells')->update(['capacity_used' => 0, 'status' => 'available']);

        // Build lookup: "blok-grup-kolom-baris" → cell
        $cellLookup = [];
        DB::table('cells')
            ->whereNotNull('blok')->whereNotNull('grup')
            ->whereNotNull('kolom')->whereNotNull('baris')
            ->where('is_active', true)
            ->select('id', 'blok', 'grup', 'kolom', 'baris', 'capacity_max')
            ->orderBy('id')
            ->each(function ($c) use (&$cellLookup) {
                $key = "{$c->blok}-{$c->grup}-{$c->kolom}-{$c->baris}";
                $cellLookup[$key] = $c;
            });

        // Akumulasi stok per cell dari cells_ref
        $cellUsed = [];   // cell_id → total stok
        $matched  = 0;
        $skipped  = 0;

        DB::table('cells_ref')->where('stok', '>', 0)->orderBy('kode')->each(function ($r) use ($cellLookup, &$cellUsed, &$matched, &$skipped) {
            $key  = "{$r->blok}-{$r->grup}-{$r->kolom}-{$r->baris}";
            $cell = $cellLookup[$key] ?? null;
            if (!$cell) { $skipped++; return; }

            $cellUsed[$cell->id] = ($cellUsed[$cell->id] ?? 0) + (int)$r->stok;
            $matched++;
        });

        // Update capacity_used + status
        $updated = 0;
        foreach ($cellUsed as $cellId => $used) {
            $capMax = DB::table('cells')->where('id', $cellId)->value('capacity_max') ?: 20;
            $status = $used >= $capMax ? 'full' : 'partial';
            DB::table('cells')->where('id', $cellId)->update([
                'capacity_used' => $used,
                'status'        => $status,
            ]);
            $updated++;
        }

        $this->newLine();
        $this->info('✅ Selesai!');
        $this->table(['Metrik', 'Nilai'], [
            ['Baris cells_ref diproses',   $refCount],
            ['Cell berhasil di-match',      $matched],
            ['Tidak ada cell (dilewati)',   $skipped],
            ['Cell di-update',              $updated],
        ]);
        $this->newLine();
        $this->warn('Untuk reset balik: php artisan refdata:reset-capacity');

        return Command::SUCCESS;
    }
}
