<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed items_ref dan cells_ref dari tabel referensi ERP (Qspart & mspart).
 * Tabel sumber (Qspart, mspart) adalah read-only dan tidak pernah dimodifikasi.
 * Tabel items & cells yang sudah ada tidak disentuh sama sekali.
 *
 * Cara jalankan:
 *   php artisan db:seed --class=RefDataSeeder
 * atau via artisan command:
 *   php artisan import:ref-data
 */
class RefDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->importItemsRef();
        $this->importCellsRef();
    }

    private function importItemsRef(): void
    {
        if (!Schema::hasTable('Qspart')) {
            $this->command->warn('⚠️  Tabel Qspart tidak ditemukan — lewati items_ref.');
            return;
        }

        $now = now();

        // Deduplicate: ambil satu baris per kode (kode ERP bisa muncul >1x di Qspart)
        $rows = DB::table('Qspart')
            ->select('kode', 'nama', 'sat', 'stock', 'minstock', 'maxstock')
            ->orderBy('kode')
            ->get()
            ->unique('kode')   // simpan yang pertama per kode
            ->values();

        DB::table('items_ref')->truncate();

        $chunks = $rows->chunk(500);
        foreach ($chunks as $chunk) {
            DB::table('items_ref')->insert(
                $chunk->map(fn($r) => [
                    'kode'       => $r->kode,
                    'nama'       => $r->nama,
                    'sat'        => $r->sat,
                    'stock'      => $r->stock    ?? 0,
                    'min_stock'  => $r->minstock ?? 0,
                    'max_stock'  => $r->maxstock ?? 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all()
            );
        }

        $this->command->info("✅ items_ref: {$rows->count()} item unik dari Qspart.");
    }

    private function importCellsRef(): void
    {
        if (!Schema::hasTable('mspart')) {
            $this->command->warn('⚠️  Tabel mspart tidak ditemukan — lewati cells_ref.');
            return;
        }

        $now = now();

        $total = DB::table('mspart')->count();
        $this->command->info("   Mengimpor {$total} baris dari mspart...");

        DB::table('cells_ref')->truncate();

        // Import in chunks to avoid memory issues (mspart has ~144K rows)
        DB::table('mspart')
            ->orderBy('kode')
            ->chunk(1000, function ($rows) use ($now) {
                DB::table('cells_ref')->insert(
                    collect($rows)->map(fn($r) => [
                        'kode'       => $r->kode,
                        'nama'       => $r->nama,
                        'stok'       => $r->stok    ?? 0,
                        'sat'        => $r->sat,
                        'minstok'    => $r->minstok ?? 0,
                        'maxstok'    => $r->maxstok ?? 0,
                        'blok'       => $r->blok,
                        'grup'       => $r->grup,
                        'kolom'      => $r->kolom,
                        'baris'      => $r->baris,
                        'note'       => $r->note,
                        'isdel'      => $r->isdel   ?? 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            });

        $this->command->info("✅ cells_ref: {$total} baris dari mspart.");
    }
}
