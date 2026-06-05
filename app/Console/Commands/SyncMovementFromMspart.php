<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sync items.movement_type from mspart.note.
 *
 * Keywords (case-insensitive):
 *   note LIKE %SERING%  →  fast_moving
 *   note LIKE %JARANG%  →  slow_moving
 *   no match / null     →  slow_moving (default)
 *
 * mspart is READ-ONLY — this command only writes to items.
 */
class SyncMovementFromMspart extends Command
{
    protected $signature   = 'items:sync-movement {--dry-run : Preview changes without writing}';
    protected $description = 'Update items.movement_type dari keyword di mspart.note (SERING → fast_moving, JARANG → slow_moving)';

    public function handle(): int
    {
        if (!Schema::hasTable('mspart')) {
            $this->error('Tabel mspart tidak ditemukan.');
            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[DRY RUN] Tidak ada perubahan yang disimpan.');
        }

        // Join mspart → items on kode = sku (case-insensitive)
        $rows = DB::select("
            SELECT
                i.id,
                i.sku,
                i.movement_type AS current_movement,
                m.note,
                CASE
                    WHEN UPPER(m.note) LIKE '%SERING%' THEN 'fast_moving'
                    ELSE 'slow_moving'
                END AS new_movement
            FROM items i
            JOIN mspart m ON UPPER(m.kode) COLLATE utf8mb4_unicode_ci = UPPER(i.sku) COLLATE utf8mb4_unicode_ci
            WHERE m.isdel = 0
              AND (
                    CASE
                        WHEN UPPER(m.note) LIKE '%SERING%' THEN 'fast_moving'
                        ELSE 'slow_moving'
                    END
                  ) != i.movement_type
            ORDER BY i.sku
        ");

        $this->info('Item yang perlu diupdate: ' . count($rows));

        if (empty($rows)) {
            $this->info('Tidak ada perubahan, items.movement_type sudah sesuai mspart.note.');
            return self::SUCCESS;
        }

        // Preview table
        $fastCount = 0;
        $slowCount = 0;
        $preview   = [];

        foreach ($rows as $r) {
            $preview[] = [$r->sku, $r->current_movement, $r->new_movement, mb_substr($r->note ?? '', 0, 60)];
            if ($r->new_movement === 'fast_moving') {
                $fastCount++;
            } else {
                $slowCount++;
            }
        }

        $this->table(['SKU', 'Sebelum', 'Sesudah', 'Note (60 char)'], array_slice($preview, 0, 30));

        if (count($rows) > 30) {
            $this->line('... dan ' . (count($rows) - 30) . ' item lainnya (tidak ditampilkan).');
        }

        $this->newLine();
        $this->line("  → fast_moving : {$fastCount}");
        $this->line("  → slow_moving : {$slowCount}");
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN selesai. Jalankan tanpa --dry-run untuk simpan perubahan.');
            return self::SUCCESS;
        }

        if (!$this->confirm('Lanjutkan update items.movement_type?', true)) {
            $this->info('Dibatalkan.');
            return self::SUCCESS;
        }

        // Batch update: pisah fast vs slow untuk efisiensi
        $fastIds = collect($rows)->where('new_movement', 'fast_moving')->pluck('id')->all();
        $slowIds = collect($rows)->where('new_movement', 'slow_moving')->pluck('id')->all();

        DB::transaction(function () use ($fastIds, $slowIds) {
            if (!empty($fastIds)) {
                DB::table('items')->whereIn('id', $fastIds)->update(['movement_type' => 'fast_moving', 'updated_at' => now()]);
            }
            if (!empty($slowIds)) {
                DB::table('items')->whereIn('id', $slowIds)->update(['movement_type' => 'slow_moving', 'updated_at' => now()]);
            }
        });

        $this->info('✅ Selesai!');
        $this->table(['Metrik', 'Jumlah'], [
            ['Diupdate → fast_moving', $fastCount],
            ['Diupdate → slow_moving', $slowCount],
            ['Total item diupdate',   count($rows)],
        ]);

        return self::SUCCESS;
    }
}
