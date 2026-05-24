<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateRandomScenario extends Command
{
    protected $signature   = 'wms:create-random-scenario';
    protected $description = 'Buat tabel mspart_random (posisi diacak) dan Qspart_random (salinan identik) untuk perbandingan 3 skenario skripsi';

    public function handle(): int
    {
        $this->info('Membuat tabel skenario random...');
        $this->warn('CATATAN: Tabel mspart dan Qspart asli TIDAK akan diubah.');
        $this->line('');

        $this->createMspartRandom();
        $this->createQspartRandom();

        $this->line('');
        $this->info('✓ Selesai!');
        $this->line('  - mspart_random : salinan mspart dengan posisi diacak (Skenario 2)');
        $this->line('  - Qspart_random : salinan identik Qspart');

        return self::SUCCESS;
    }

    private function createMspartRandom(): void
    {
        $this->line('  → Membuat mspart_random...');

        DB::statement('DROP TABLE IF EXISTS mspart_random');
        DB::statement('CREATE TABLE mspart_random LIKE mspart');
        DB::statement('INSERT INTO mspart_random SELECT * FROM mspart');

        // Ambil semua item yang punya posisi valid
        $items = DB::table('mspart_random')
            ->whereNotNull('blok')
            ->where('blok', '!=', '')
            ->where('blok', '!=', '0')
            ->select('kode', 'blok', 'grup', 'kolom', 'baris')
            ->get();

        if ($items->isEmpty()) {
            $this->warn('  ! Tidak ada item dengan posisi valid di mspart_random, skip shuffle.');
            return;
        }

        $kodes     = $items->pluck('kode')->toArray();
        $positions = $items->map(fn($r) => [
            'blok'  => $r->blok,
            'grup'  => $r->grup,
            'kolom' => $r->kolom,
            'baris' => $r->baris,
        ])->toArray();

        // Fisher-Yates shuffle — pastikan minimal 1 posisi berpindah
        do {
            shuffle($positions);
        } while ($this->isIdenticalShuffle($items, $positions));

        DB::beginTransaction();
        try {
            foreach ($kodes as $i => $kode) {
                DB::table('mspart_random')
                    ->where('kode', $kode)
                    ->update([
                        'blok'  => $positions[$i]['blok'],
                        'grup'  => $positions[$i]['grup'],
                        'kolom' => $positions[$i]['kolom'],
                        'baris' => $positions[$i]['baris'],
                    ]);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('  ✗ Gagal update posisi: ' . $e->getMessage());
            throw $e;
        }

        $count = count($kodes);
        $this->line("  ✓ {$count} item posisinya diacak di mspart_random.");
    }

    private function createQspartRandom(): void
    {
        $this->line('  → Membuat Qspart_random...');

        DB::statement('DROP TABLE IF EXISTS Qspart_random');
        DB::statement('CREATE TABLE Qspart_random LIKE Qspart');
        DB::statement('INSERT INTO Qspart_random SELECT * FROM Qspart');

        $count = DB::table('Qspart_random')->count();
        $this->line("  ✓ {$count} baris disalin ke Qspart_random.");
    }

    private function isIdenticalShuffle($items, array $shuffled): bool
    {
        foreach ($items as $i => $item) {
            if ($item->blok !== $shuffled[$i]['blok'] || $item->kolom !== $shuffled[$i]['kolom']) {
                return false;
            }
        }
        return true;
    }
}
