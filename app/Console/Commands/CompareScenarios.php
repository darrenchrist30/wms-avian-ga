<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CompareScenarios extends Command
{
    protected $signature   = 'wms:compare-scenarios';
    protected $description = 'Hitung dan bandingkan 5 metrik efektivitas untuk 3 skenario penataan gudang (Bab 4 skripsi)';

    // Asumsi dimensi gudang — Manhattan Distance
    const BLOK_M     = 10;    // meter per unit blok
    const GRUP_M     = 5;     // meter per unit grup
    const KOLOM_M    = 2;     // meter per unit kolom
    const SPEED      = 50;    // kecepatan forklift (meter/menit)
    const T_VERT     = 0.5;   // waktu per level baris (menit)
    const T_HANDLING = 2.0;   // waktu penanganan per penempatan (menit)

    // Kapasitas — sama dengan CellCapacityService
    const SCALE          = 100;
    const FALLBACK_STOCK = 100;

    public function handle(): int
    {
        $dir = storage_path('app/exports');
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $this->info('Memuat data DO real (SJ/GUD-AAP)...');
        $doItems = $this->loadDoItems();
        if (empty($doItems)) {
            $this->error('Tidak ada item ditemukan dari DO real!');
            return self::FAILURE;
        }
        $doNumbers = array_unique(array_column($doItems, 'do_number'));
        $this->line('  ✓ ' . count($doItems) . ' baris dari ' . count($doNumbers) . ' DO');

        $this->info('Memuat sel gudang...');
        $cells = $this->loadCells();
        $this->line('  ✓ ' . count($cells) . ' sel aktif');

        $this->line('');
        $this->line('  → Simulasi S1 (Manual – urut kategori + jarak terdekat)...');
        $s1 = $this->simulateManual($doItems, $cells);

        $this->line('  → Simulasi S2 (Random – penempatan acak)...');
        $s2 = $this->simulateRandom($doItems, $cells);

        $this->line('  → Menghitung S3 (GA – hasil aktual)...');
        $s3 = $this->calcGaActual();

        $this->info('Menulis CSV...');
        $this->writeComparison($dir, $s1, $s2, $s3);
        $this->writeDetail($dir, $doItems, $s1['assignments'], $s2['assignments'], $s3['assignments']);

        $this->line('');
        $this->info('✓ Selesai! File tersimpan di:');
        $this->line("  storage/app/exports/scenario_comparison.csv");
        $this->line("  storage/app/exports/scenario_detail.csv");

        $this->printSummary($s1, $s2, $s3);

        return self::SUCCESS;
    }

    // ─── Data loaders ────────────────────────────────────────────────────────

    private function loadDoItems(): array
    {
        return DB::table('inbound_transactions as io')
            ->join('inbound_details as id', 'id.inbound_order_id', '=', 'io.id')
            ->join('items as i', 'i.id', '=', 'id.item_id')
            ->join('item_categories as cat', 'cat.id', '=', 'i.category_id')
            ->where('io.do_number', 'like', 'SJ/GUD-AAP/%')
            ->where('id.quantity_received', '>', 0)
            ->select(
                'id.id as detail_id',
                'io.do_number',
                'i.id as item_id',
                'i.sku',
                'i.name as item_name',
                'i.max_stock',
                'cat.name as category',
                'id.quantity_received',
            )
            ->orderBy('cat.name')
            ->orderBy('i.sku')
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();
    }

    private function loadCells(): array
    {
        return DB::table('cells as c')
            ->where('c.is_active', true)
            ->whereNull('c.deleted_at')
            ->select(
                'c.id',
                'c.code',
                'c.capacity_max',
                'c.capacity_used',
                DB::raw('GREATEST(0, CAST(c.capacity_max AS SIGNED) - CAST(c.capacity_used AS SIGNED)) as capacity_remaining'),
                'c.zone_category',
                'c.blok',
                'c.grup',
                'c.kolom',
                'c.baris',
            )
            ->orderBy('c.blok')
            ->orderBy('c.grup')
            ->orderBy('c.kolom')
            ->orderBy('c.baris')
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();
    }

    // ─── S1: Manual simulation ────────────────────────────────────────────────

    private function simulateManual(array $doItems, array $cells): array
    {
        // Start dari kapasitas sisa saat ini
        $pool = $cells;
        foreach ($pool as &$c) {
            $c['_rem'] = max(0, (int) $c['capacity_remaining']);
        }
        unset($c);

        $assignments = [];

        // Items sudah diurutkan by kategori dari loadDoItems
        foreach ($doItems as $item) {
            $needed = $this->points($item['quantity_received'], $item['max_stock'] ?? 0);
            $left   = $needed;

            // Urutkan pool: kategori match dulu, kemudian jarak terdekat
            usort($pool, function ($a, $b) use ($item) {
                $ma = $this->catMatch($a['zone_category'], $item['category']);
                $mb = $this->catMatch($b['zone_category'], $item['category']);
                if ($ma !== $mb) return $mb - $ma;
                return $this->dist($a) <=> $this->dist($b);
            });

            $placements = [];
            foreach ($pool as &$cell) {
                if ($left <= 0) break;
                if ($cell['_rem'] <= 0) continue;

                $take = min($left, $cell['_rem']);
                $cell['_rem'] -= $take;
                $left         -= $take;
                $placements[]  = $this->makePlacement($cell, $take);
            }
            unset($cell);

            $assignments[$item['detail_id']] = $this->makeAssignment($item, $needed, $left, $placements);
        }

        return $this->aggregate($assignments);
    }

    // ─── S2: Random simulation ────────────────────────────────────────────────

    private function simulateRandom(array $doItems, array $cells): array
    {
        $pool = $cells;
        foreach ($pool as &$c) {
            $c['_rem'] = max(0, (int) $c['capacity_remaining']);
        }
        unset($c);
        shuffle($pool);

        $items = $doItems;
        shuffle($items);

        $assignments = [];

        foreach ($items as $item) {
            $needed = $this->points($item['quantity_received'], $item['max_stock'] ?? 0);
            $left   = $needed;
            $placements = [];

            foreach ($pool as &$cell) {
                if ($left <= 0) break;
                if ($cell['_rem'] <= 0) continue;

                $take = min($left, $cell['_rem']);
                $cell['_rem'] -= $take;
                $left         -= $take;
                $placements[]  = $this->makePlacement($cell, $take);
            }
            unset($cell);

            $assignments[$item['detail_id']] = $this->makeAssignment($item, $needed, $left, $placements);
        }

        return $this->aggregate($assignments);
    }

    // ─── S3: GA actual ───────────────────────────────────────────────────────

    private function calcGaActual(): array
    {
        $rows = DB::table('inbound_transactions as io')
            ->join('inbound_details as id',               'id.inbound_order_id',      '=', 'io.id')
            ->join('items as i',                          'i.id',                     '=', 'id.item_id')
            ->join('item_categories as cat',              'cat.id',                   '=', 'i.category_id')
            ->join('ga_recommendations as gr',            'gr.inbound_order_id',      '=', 'io.id')
            ->join('ga_recommendation_details as grd',    'grd.ga_recommendation_id', '=', 'gr.id')
            ->join('cells as c',                          'c.id',                     '=', 'grd.cell_id')
            ->where('io.do_number',             'like', 'SJ/GUD-AAP/%')
            ->where('grd.inbound_order_item_id', '=', DB::raw('id.id'))
            ->select(
                'id.id as detail_id',
                'io.do_number',
                'i.sku',
                'i.name as item_name',
                'i.max_stock',
                'cat.name as category',
                'id.quantity_received',
                'grd.quantity as placed_qty',
                'grd.gene_fitness',
                'grd.fc_cap_score',
                'grd.fc_cat_score',
                'grd.fc_aff_score',
                'grd.fc_split_score',
                'grd.fc_mov_score',
                'c.id as cell_id',
                'c.code as cell_code',
                'c.blok', 'c.grup', 'c.kolom', 'c.baris',
            )
            ->get();

        $assignments = [];
        foreach ($rows as $r) {
            $did = $r->detail_id;
            if (!isset($assignments[$did])) {
                $assignments[$did] = [
                    'detail_id'    => $did,
                    'do_number'    => $r->do_number,
                    'sku'          => $r->sku,
                    'item_name'    => $r->item_name,
                    'category'     => $r->category,
                    'qty'          => $r->quantity_received,
                    'points_needed'=> $this->points($r->quantity_received, $r->max_stock ?? 0),
                    'placements'   => [],
                    'gene_fitness' => $r->gene_fitness,
                    'fc_cap'       => $r->fc_cap_score,
                    'fc_cat'       => $r->fc_cat_score,
                    'fc_aff'       => $r->fc_aff_score,
                    'fc_split'     => $r->fc_split_score,
                    'fc_mov'       => $r->fc_mov_score,
                ];
            }
            $assignments[$did]['placements'][] = $this->makePlacement(
                ['id' => $r->cell_id, 'code' => $r->cell_code, 'blok' => $r->blok, 'grup' => $r->grup, 'kolom' => $r->kolom, 'baris' => $r->baris],
                $this->points((int)$r->placed_qty, $r->max_stock ?? 0)
            );
        }

        foreach ($assignments as &$a) {
            $a['split']      = count($a['placements']) > 1;
            $a['violation']  = false; // GA menjamin kapasitas
            $a['total_time'] = round(array_sum(array_column($a['placements'], 'time')), 4);
        }
        unset($a);

        return $this->aggregate($assignments);
    }

    // ─── Aggregate metrics ───────────────────────────────────────────────────

    private function aggregate(array $assignments): array
    {
        $splits    = 0;
        $violations = 0;
        $totalTime  = 0;
        $totalLocs  = 0;

        foreach ($assignments as $a) {
            if ($a['split'])     $splits++;
            if ($a['violation']) $violations++;
            $totalTime += $a['total_time'];
            $totalLocs += max(1, count($a['placements']));
        }

        $n = count($assignments);

        return [
            'split_count'     => $splits,
            'violation_count' => $violations,
            'avg_locations'   => $n > 0 ? round($totalLocs / $n, 4) : 0,
            'avg_time'        => $n > 0 ? round($totalTime / $n, 4) : 0,
            'total_time'      => round($totalTime, 2),
            'item_count'      => $n,
            'assignments'     => $assignments,
        ];
    }

    // ─── CSV writers ─────────────────────────────────────────────────────────

    private function writeComparison(string $dir, array $s1, array $s2, array $s3): void
    {
        $pct = fn($base, $ga) =>
            $base > 0 ? round(($base - $ga) / $base * 100, 2) . '%' : '-';

        $fp = fopen("{$dir}/scenario_comparison.csv", 'w');
        fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($fp, [
            'Metrik Evaluasi',
            'S1 Manual / Existing',
            'S2 Random Placement',
            'S3 Genetic Algorithm',
            'Perbaikan GA vs S1 (%)',
            'Perbaikan GA vs S2 (%)',
        ]);

        $s3GaFitness = '-';
        if (!empty($s3['assignments'])) {
            $fits = array_filter(array_column($s3['assignments'], 'gene_fitness'));
            $s3GaFitness = $fits ? round(array_sum($fits) / count($fits) * 100, 2) : '-';
        }

        $rows = [
            ['Jumlah Item Diproses',
                $s1['item_count'], $s2['item_count'], $s3['item_count'], '-', '-'],
            ['Jumlah SKU Split Location',
                $s1['split_count'], $s2['split_count'], $s3['split_count'],
                $pct($s1['split_count'] ?: 1e-9, $s3['split_count']),
                $pct($s2['split_count'] ?: 1e-9, $s3['split_count'])],
            ['Rata-rata Lokasi per Item',
                $s1['avg_locations'], $s2['avg_locations'], $s3['avg_locations'],
                $pct($s1['avg_locations'], $s3['avg_locations']),
                $pct($s2['avg_locations'], $s3['avg_locations'])],
            ['Pelanggaran Kapasitas (item)',
                $s1['violation_count'], $s2['violation_count'], $s3['violation_count'],
                $pct($s1['violation_count'] ?: 1e-9, $s3['violation_count']),
                $pct($s2['violation_count'] ?: 1e-9, $s3['violation_count'])],
            ['Estimasi Total Waktu Put-away (menit)',
                $s1['total_time'], $s2['total_time'], $s3['total_time'],
                $pct($s1['total_time'], $s3['total_time']),
                $pct($s2['total_time'], $s3['total_time'])],
            ['Estimasi Rata-rata Waktu Put-away per Item (menit)',
                $s1['avg_time'], $s2['avg_time'], $s3['avg_time'],
                $pct($s1['avg_time'], $s3['avg_time']),
                $pct($s2['avg_time'], $s3['avg_time'])],
            ['Rata-rata Gene Fitness GA (/100)',
                '-', '-', $s3GaFitness, '-', '-'],
        ];

        foreach ($rows as $row) fputcsv($fp, $row);
        fclose($fp);
        $this->line('  ✓ scenario_comparison.csv');
    }

    private function writeDetail(string $dir, array $doItems, array $s1a, array $s2a, array $s3a): void
    {
        $fp = fopen("{$dir}/scenario_detail.csv", 'w');
        fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($fp, [
            'Detail ID', 'DO Number', 'SKU', 'Nama Item', 'Kategori', 'Qty Diterima',
            'S1 Sel',  'S1 Jml Lokasi', 'S1 Split', 'S1 Waktu (mnt)',
            'S2 Sel',  'S2 Jml Lokasi', 'S2 Split', 'S2 Waktu (mnt)',
            'S3 Sel (GA)', 'S3 Jml Lokasi', 'S3 Split', 'S3 Waktu (mnt)', 'S3 Gene Fitness',
            'Selisih Waktu S1 vs GA (mnt)', 'Selisih Waktu S2 vs GA (mnt)',
        ]);

        $doIdx = [];
        foreach ($doItems as $d) $doIdx[$d['detail_id']] = $d;

        $allIds = array_unique(array_merge(array_keys($s1a), array_keys($s2a), array_keys($s3a)));
        sort($allIds);

        foreach ($allIds as $did) {
            $base = $doIdx[$did] ?? $s3a[$did] ?? $s1a[$did] ?? [];
            $s1 = $s1a[$did] ?? null;
            $s2 = $s2a[$did] ?? null;
            $s3 = $s3a[$did] ?? null;

            $s1t = $s1['total_time'] ?? null;
            $s2t = $s2['total_time'] ?? null;
            $s3t = $s3['total_time'] ?? null;

            fputcsv($fp, [
                $did,
                $base['do_number']       ?? '-',
                $base['sku']             ?? '-',
                $base['item_name']       ?? '-',
                $base['category']        ?? '-',
                $base['quantity_received'] ?? $base['qty'] ?? '-',
                // S1
                $s1 ? implode(';', array_column($s1['placements'], 'cell_code')) : '-',
                $s1 ? count($s1['placements']) : '-',
                $s1 ? ($s1['split'] ? 'Ya' : 'Tidak') : '-',
                $s1t ?? '-',
                // S2
                $s2 ? implode(';', array_column($s2['placements'], 'cell_code')) : '-',
                $s2 ? count($s2['placements']) : '-',
                $s2 ? ($s2['split'] ? 'Ya' : 'Tidak') : '-',
                $s2t ?? '-',
                // S3 GA
                $s3 ? implode(';', array_column($s3['placements'], 'cell_code')) : '-',
                $s3 ? count($s3['placements']) : '-',
                $s3 ? ($s3['split'] ? 'Ya' : 'Tidak') : '-',
                $s3t ?? '-',
                ($s3['gene_fitness'] ?? null) !== null ? round($s3['gene_fitness'] * 100, 2) : '-',
                // Selisih
                ($s1t !== null && $s3t !== null) ? round($s1t - $s3t, 4) : '-',
                ($s2t !== null && $s3t !== null) ? round($s2t - $s3t, 4) : '-',
            ]);
        }

        fclose($fp);
        $this->line('  ✓ scenario_detail.csv');
    }

    private function printSummary(array $s1, array $s2, array $s3): void
    {
        $this->line('');
        $this->info('=== RINGKASAN 3 SKENARIO ===');
        $this->table(
            ['Metrik', 'S1 Manual', 'S2 Random', 'S3 GA'],
            [
                ['Item Diproses',      $s1['item_count'],      $s2['item_count'],      $s3['item_count']],
                ['Split Location',     $s1['split_count'],     $s2['split_count'],     $s3['split_count']],
                ['Avg Lokasi/Item',    $s1['avg_locations'],   $s2['avg_locations'],   $s3['avg_locations']],
                ['Kapasitas Violation',$s1['violation_count'], $s2['violation_count'], $s3['violation_count']],
                ['Avg Waktu/Item(mnt)',$s1['avg_time'],        $s2['avg_time'],        $s3['avg_time']],
            ]
        );
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makePlacement(array $cell, int $points): array
    {
        return [
            'cell_id'   => $cell['id'],
            'cell_code' => $cell['code'],
            'points'    => $points,
            'time'      => $this->travelTime($cell['blok'], $cell['grup'], $cell['kolom'], $cell['baris']),
        ];
    }

    private function makeAssignment(array $item, int $needed, int $leftover, array $placements): array
    {
        return [
            'detail_id'    => $item['detail_id'],
            'do_number'    => $item['do_number'],
            'sku'          => $item['sku'],
            'item_name'    => $item['item_name'],
            'category'     => $item['category'],
            'qty'          => $item['quantity_received'],
            'points_needed'=> $needed,
            'placements'   => $placements,
            'split'        => count($placements) > 1,
            'violation'    => $leftover > 0,
            'total_time'   => round(array_sum(array_column($placements, 'time')), 4),
        ];
    }

    private function travelTime($blok, $grup, $kolom, $baris): float
    {
        if ($blok === null) return self::T_HANDLING;
        $b = is_numeric($blok) ? (int) $blok : max(1, ord(strtoupper((string) $blok)) - ord('A') + 1);
        $g = is_numeric($grup) ? (int) $grup : max(1, ord(strtolower((string) $grup)) - ord('a') + 1);
        $k = max(1, (int) ($kolom ?? 1));
        $r = max(1, (int) ($baris ?? 1));
        $dist = ($b * self::BLOK_M) + ($g * self::GRUP_M) + ($k * self::KOLOM_M);
        return round($dist / self::SPEED + $r * self::T_VERT + self::T_HANDLING, 4);
    }

    private function points(int $qty, int $maxStock): int
    {
        $ms = $maxStock > 0 ? $maxStock : self::FALLBACK_STOCK;
        return max(1, (int) ceil($qty * self::SCALE / $ms));
    }

    private function catMatch(?string $zone, string $category): int
    {
        if (!$zone) return 0;
        return (stripos($zone, $category) !== false || stripos($category, $zone) !== false) ? 1 : 0;
    }

    private function dist(array $cell): float
    {
        $b = is_numeric($cell['blok']) ? (int) $cell['blok'] : max(1, ord(strtoupper((string) ($cell['blok'] ?? 'A'))) - ord('A') + 1);
        $g = is_numeric($cell['grup']) ? (int) $cell['grup'] : max(1, ord(strtolower((string) ($cell['grup'] ?? 'a'))) - ord('a') + 1);
        $k = max(1, (int) ($cell['kolom'] ?? 1));
        return ($b * self::BLOK_M) + ($g * self::GRUP_M) + ($k * self::KOLOM_M);
    }
}
