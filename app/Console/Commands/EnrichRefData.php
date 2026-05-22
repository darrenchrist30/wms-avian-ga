<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enriches cells_ref dengan:
 *  - Lokasi valid (blok 1-2, grup A-H, kolom 1-7, baris 1-9) untuk SEMUA item
 *  - Stok > 0 untuk SEMUA item (gunakan real stok jika ada, kalau 0/null hitung dari min/max)
 *
 * Aturan lokasi:
 *  1. Blok 1-2, grup A-H, kolom/baris valid  → pakai data real persis
 *  2. Blok 3-6 dengan grup A-H valid          → remap blok (ganjil→1, genap→2), pertahankan grup/kolom/baris
 *  3. NULL / kolom-baris invalid              → assign dari pool sel yang tersedia
 */
class EnrichRefData extends Command
{
    protected $signature   = 'refdata:enrich';
    protected $description = 'Isi lokasi & stok yang kosong di cells_ref dengan nilai yang masuk akal.';

    // Warehouse cell bounds
    private const BLOKS  = [1, 2];
    private const GRUPS  = ['A','B','C','D','E','F','G','H'];
    private const KOLOMS = [1,2,3,4,5,6,7];
    private const BARIS  = [1,2,3,4,5,6,7,8,9];

    public function handle(): int
    {
        if (!Schema::hasTable('cells_ref') || !Schema::hasTable('mspart') || !Schema::hasTable('Qspart')) {
            $this->error('Pastikan cells_ref, mspart, dan Qspart sudah ada. Jalankan migrate + import:ref-data dulu.');
            return Command::FAILURE;
        }

        $this->info('Membaca data mspart + Qspart...');

        // Ambil semua item dari mspart + join min/max dari Qspart
        $rows = DB::select("
            SELECT
                m.kode, m.nama, m.sat, m.stok,
                TRIM(m.blok)  AS blok,
                TRIM(m.grup)  AS grup,
                TRIM(m.kolom) AS kolom,
                TRIM(m.baris) AS baris,
                m.note, m.isdel,
                COALESCE(q.minstock, m.minstok, 0) AS min_stock,
                COALESCE(q.maxstock, m.maxstok, 0) AS max_stock
            FROM mspart m
            LEFT JOIN (
                SELECT kode,
                       MIN(minstock) AS minstock,
                       MAX(maxstock) AS maxstock
                FROM Qspart GROUP BY kode
            ) q ON q.kode = m.kode
            WHERE m.isdel = 0
            ORDER BY m.kode
        ");

        $this->info('  ' . count($rows) . ' baris ditemukan.');

        // ── Pisahkan: good vs needs-location ─────────────────────────────
        $goodLoc   = [];   // sudah punya lokasi bagus
        $needsLoc  = [];   // harus di-assign

        foreach ($rows as $r) {
            if ($this->isGoodLocation((string)$r->blok, (string)$r->grup, (string)$r->kolom, (string)$r->baris)) {
                $goodLoc[] = $r;
            } elseif ($this->isRemappable((string)$r->blok, (string)$r->grup, (string)$r->kolom, (string)$r->baris)) {
                // Remap blok 3/4/5/6 → 1/2
                $newBlok = ((int)$r->blok % 2 === 1) ? 1 : 2;   // ganjil→1, genap→2
                $r->blok  = (string)$newBlok;
                $goodLoc[] = $r;
            } else {
                $needsLoc[] = $r;
            }
        }

        $this->info('  Good location  : ' . count($goodLoc));
        $this->info('  Needs location : ' . count($needsLoc));

        // ── Build cell pool (blok 1-2, grup A-H, kolom 1-7, baris 1-9) ──
        // Tandai sel yang sudah digunakan (boleh multi-item per sel, max ~12)
        $cellUsage = [];   // key = "blok-grup-kolom-baris" → count
        foreach ($goodLoc as $r) {
            $key = "{$r->blok}-{$r->grup}-{$r->kolom}-{$r->baris}";
            $cellUsage[$key] = ($cellUsage[$key] ?? 0) + 1;
        }

        // Pool sel tersedia (terurut: blok, grup, kolom, baris)
        $pool = [];
        foreach (self::BLOKS as $b) {
            foreach (self::GRUPS as $g) {
                foreach (self::KOLOMS as $k) {
                    foreach (self::BARIS as $r) {
                        $pool[] = ['blok' => $b, 'grup' => $g, 'kolom' => $k, 'baris' => $r];
                    }
                }
        }
        }

        // ── Assign lokasi ke needsLoc items ──────────────────────────────
        // Rotasi pool: skip sel yang sudah penuh (> 12 items)
        $poolIdx = 0;
        $maxPerCell = 12;

        foreach ($needsLoc as &$item) {
            // Cari sel berikutnya yang belum melebihi batas
            $attempts = 0;
            while ($attempts < count($pool)) {
                $cell = $pool[$poolIdx % count($pool)];
                $key  = "{$cell['blok']}-{$cell['grup']}-{$cell['kolom']}-{$cell['baris']}";
                if (($cellUsage[$key] ?? 0) < $maxPerCell) {
                    $item->blok  = (string)$cell['blok'];
                    $item->grup  = $cell['grup'];
                    $item->kolom = (string)$cell['kolom'];
                    $item->baris = (string)$cell['baris'];
                    $cellUsage[$key] = ($cellUsage[$key] ?? 0) + 1;
                    $poolIdx++;
                    break;
                }
                $poolIdx++;
                $attempts++;
            }
        }
        unset($item);

        // ── Gabung kembali ────────────────────────────────────────────────
        $allItems = array_merge($goodLoc, $needsLoc);

        // ── Fill stok yang 0/null ─────────────────────────────────────────
        foreach ($allItems as &$item) {
            if ((float)($item->stok) > 0) {
                continue;   // punya stok real, pertahankan
            }
            $min = (float)$item->min_stock;
            $max = (float)$item->max_stock;

            if ($max > 0) {
                // Pakai rata-rata min+max, minimal 1
                $item->stok = max(1, (int)round(($min + $max) / 2));
            } elseif ($min > 0) {
                $item->stok = max(1, (int)$min);
            } else {
                // Tidak ada info → random kecil 2-15
                $item->stok = mt_rand(2, 15);
            }
        }
        unset($item);

        // ── Tulis ke cells_ref ────────────────────────────────────────────
        $this->info('Menulis ' . count($allItems) . ' baris ke cells_ref...');
        $now = now();

        DB::table('cells_ref')->truncate();

        $chunks = array_chunk($allItems, 500);
        foreach ($chunks as $chunk) {
            DB::table('cells_ref')->insert(
                array_map(fn($r) => [
                    'kode'       => $r->kode,
                    'nama'       => $r->nama,
                    'stok'       => $r->stok,
                    'sat'        => $r->sat,
                    'minstok'    => $r->min_stock,
                    'maxstok'    => $r->max_stock,
                    'blok'       => $r->blok,
                    'grup'       => $r->grup,
                    'kolom'      => $r->kolom,
                    'baris'      => $r->baris,
                    'note'       => $r->note ?? null,
                    'isdel'      => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk)
            );
        }

        // ── Summary ───────────────────────────────────────────────────────
        $stats = DB::table('cells_ref')->selectRaw("
            COUNT(*) as total,
            COUNT(DISTINCT CONCAT(blok,'-',grup,'-',kolom,'-',baris)) as unique_cells,
            SUM(CASE WHEN stok > 0 THEN 1 ELSE 0 END) as has_stock,
            MIN(stok) as min_stok, MAX(stok) as max_stok, ROUND(AVG(stok),1) as avg_stok
        ")->first();

        $this->newLine();
        $this->info('✅ cells_ref enrichment selesai:');
        $this->table(['Metrik', 'Nilai'], [
            ['Total item',        $stats->total],
            ['Sel unik dipakai',  $stats->unique_cells],
            ['Item punya stok',   $stats->has_stock],
            ['Stok min / max',    $stats->min_stok . ' / ' . $stats->max_stok],
            ['Stok rata-rata',    $stats->avg_stok],
        ]);

        return Command::SUCCESS;
    }

    // Lokasi valid langsung pakai: blok 1/2, grup A-H, kolom 1-7, baris 1-9
    private function isGoodLocation(string $blok, string $grup, string $kolom, string $baris): bool
    {
        return in_array($blok, ['1','2'])
            && preg_match('/^[A-H]$/i', $grup)
            && (int)$kolom >= 1 && (int)$kolom <= 7
            && (int)$baris  >= 1 && (int)$baris  <= 9;
    }

    // Bisa di-remap: blok 3-6, grup A-H valid, kolom/baris valid
    private function isRemappable(string $blok, string $grup, string $kolom, string $baris): bool
    {
        return in_array($blok, ['3','4','5','6'])
            && preg_match('/^[A-H]$/i', $grup)
            && (int)$kolom >= 1 && (int)$kolom <= 7
            && (int)$baris  >= 1 && (int)$baris  <= 9;
    }
}
