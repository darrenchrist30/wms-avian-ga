<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Membandingkan 3 skenario penataan gudang menggunakan metrik operasional
 * dan fitness function yang identik dengan GA engine (fitness.py).
 *
 * Fitness = FC_CAP(30) + FC_CAT(25) + FC_AFF(20) + FC_SPLIT(15) + FC_MOV(10) = 100
 */
class CompareScenarios extends Command
{
    protected $signature   = 'wms:compare-scenarios';
    protected $description = 'Bandingkan 3 skenario penataan gudang dengan metrik operasional + fitness score (Bab 4 skripsi)';

    // Manhattan Distance — put-away time
    const BLOK_M     = 10;
    const GRUP_M     = 5;
    const KOLOM_M    = 2;
    const SPEED      = 50;    // m/menit
    const T_VERT     = 0.5;   // menit/level
    const T_HANDLING = 2.0;   // menit/penempatan

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
            $this->error('Tidak ada item dari DO real!');
            return self::FAILURE;
        }
        $doNumbers = array_unique(array_column($doItems, 'do_number'));
        $this->line('  ✓ ' . count($doItems) . ' baris dari ' . count($doNumbers) . ' DO');

        $this->info('Memuat sel gudang...');
        $cells = $this->loadCells();
        $this->line('  ✓ ' . count($cells) . ' sel aktif');

        $this->info('Memuat data pendukung fitness...');
        $itemIds         = array_unique(array_column($doItems, 'item_id'));
        $affinityMap     = $this->loadAffinityMap($itemIds);
        $existingCells   = $this->loadExistingItemCells($itemIds);
        $cellsById       = array_column($cells, null, 'id');
        $this->line('  ✓ ' . count($affinityMap) . ' pasangan afinitas');

        $this->line('');
        $this->line('  → Simulasi S1 (Manual – urut kategori + jarak terdekat)...');
        $s1assignments = $this->simulateManual($doItems, $cells);
        $s1fitness     = $this->calcSimFitness($s1assignments, $cellsById, $existingCells);
        $s1            = $this->aggregate($s1assignments, $s1fitness);

        $this->line('  → Simulasi S2 (Random – penempatan acak)...');
        $s2assignments = $this->simulateRandom($doItems, $cells);
        $s2fitness     = $this->calcSimFitness($s2assignments, $cellsById, $existingCells);
        $s2            = $this->aggregate($s2assignments, $s2fitness);

        $this->line('  → Menghitung S3 (GA – hasil aktual)...');
        $s3 = $this->calcGaActual();

        $this->info('Menulis CSV...');
        $this->writeComparison($dir, $s1, $s2, $s3);
        $this->writeDetail($dir, $doItems, $s1assignments, $s2assignments, $s3['assignments']);

        $this->line('');
        $this->info('✓ Selesai!');
        $this->line('  storage/app/exports/scenario_comparison.csv');
        $this->line('  storage/app/exports/scenario_detail.csv');

        $this->printSummary($s1, $s2, $s3);

        return self::SUCCESS;
    }

    // ─── Data loaders ────────────────────────────────────────────────────────

    private function loadDoItems(): array
    {
        return DB::table('inbound_transactions as io')
            ->join('inbound_details as id',      'id.inbound_order_id', '=', 'io.id')
            ->join('items as i',                  'i.id',               '=', 'id.item_id')
            ->join('item_categories as cat',      'cat.id',             '=', 'i.category_id')
            ->where('io.do_number', 'like', 'SJ/GUD-AAP/%')
            ->where('id.quantity_received', '>', 0)
            ->select(
                'id.id as detail_id',
                'io.do_number',
                'i.id as item_id',
                'i.sku',
                'i.name as item_name',
                'i.max_stock',
                'i.movement_type',
                'i.category_id',
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
            ->leftJoin('racks as r', 'r.id', '=', 'c.rack_id')
            ->where('c.is_active', true)
            ->whereNull('c.deleted_at')
            ->select(
                'c.id',
                'c.code',
                'c.capacity_max',
                'c.capacity_used',
                DB::raw('GREATEST(0, CAST(c.capacity_max AS SIGNED) - CAST(c.capacity_used AS SIGNED)) as capacity_remaining'),
                'c.zone_category',
                'c.dominant_category_id',
                'c.blok',
                'c.grup',
                'c.kolom',
                'c.baris',
                'r.code as rack_code',
            )
            ->orderBy('c.blok')
            ->orderBy('c.grup')
            ->orderBy('c.kolom')
            ->orderBy('c.baris')
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();
    }

    private function loadAffinityMap(array $itemIds): array
    {
        $rows = DB::table('item_affinities')
            ->whereIn('item_id', $itemIds)
            ->orWhereIn('related_item_id', $itemIds)
            ->select('item_id', 'related_item_id', 'affinity_score')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[$r->item_id][$r->related_item_id] = (float) $r->affinity_score;
            $map[$r->related_item_id][$r->item_id] = (float) $r->affinity_score;
        }
        return $map;
    }

    private function loadExistingItemCells(array $itemIds): array
    {
        $rows = DB::table('stock_records')
            ->whereIn('item_id', $itemIds)
            ->where('status', 'available')
            ->where('quantity', '>', 0)
            ->select('item_id', 'cell_id')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[$r->item_id][] = $r->cell_id;
        }
        return $map;
    }

    // ─── S1: Manual simulation ────────────────────────────────────────────────

    private function simulateManual(array $doItems, array $cells): array
    {
        $pool = $cells;
        foreach ($pool as &$c) {
            $c['_rem'] = max(0, (int) $c['capacity_remaining']);
        }
        unset($c);

        $assignments = [];
        foreach ($doItems as $item) {
            $needed = $this->points($item['quantity_received'], $item['max_stock'] ?? 0);
            $left   = $needed;

            // S1 = manual realistis: pilih sel terdekat dari pintu masuk,
            // tanpa mempertimbangkan kategori (pekerja cari slot kosong terdekat).
            usort($pool, fn($a, $b) => $this->dist($a) <=> $this->dist($b));

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

        return $assignments;
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

        return $assignments;
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
            ->where('io.do_number',              'like', 'SJ/GUD-AAP/%')
            ->where('grd.inbound_order_item_id', '=',   DB::raw('id.id'))
            ->select(
                'id.id as detail_id',
                'io.do_number',
                'i.id as item_id',
                'i.sku',
                'i.name as item_name',
                'i.max_stock',
                'i.movement_type',
                'i.category_id',
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
                    'item_id'      => $r->item_id,
                    'sku'          => $r->sku,
                    'item_name'    => $r->item_name,
                    'category'     => $r->category,
                    'category_id'  => $r->category_id,
                    'movement_type'=> $r->movement_type,
                    'qty'          => $r->quantity_received,
                    'points_needed'=> $this->points($r->quantity_received, $r->max_stock ?? 0),
                    'placements'   => [],
                    'gene_fitness' => 0,
                    'fc_cap'  => 0, 'fc_cat' => 0, 'fc_aff' => 0,
                    'fc_split'=> 0, 'fc_mov' => 0,
                ];
            }
            $assignments[$did]['placements'][] = $this->makePlacement(
                ['id' => $r->cell_id, 'code' => $r->cell_code,
                 'blok' => $r->blok, 'grup' => $r->grup,
                 'kolom' => $r->kolom, 'baris' => $r->baris],
                $this->points((int) $r->placed_qty, $r->max_stock ?? 0)
            );
            // Accumulate fitness from each gene
            $assignments[$did]['gene_fitness'] += (float) $r->gene_fitness;
            $assignments[$did]['fc_cap']   += (float) $r->fc_cap_score;
            $assignments[$did]['fc_cat']   += (float) $r->fc_cat_score;
            $assignments[$did]['fc_aff']   += (float) $r->fc_aff_score;
            $assignments[$did]['fc_split'] += (float) $r->fc_split_score;
            $assignments[$did]['fc_mov']   += (float) $r->fc_mov_score;
        }

        foreach ($assignments as &$a) {
            $n = max(1, count($a['placements']));
            // Average across genes (placements)
            $a['gene_fitness'] = round($a['gene_fitness'] / $n, 4);  // already 0-100 scale
            $a['fc_cap']       = round($a['fc_cap']   / $n, 4);
            $a['fc_cat']       = round($a['fc_cat']   / $n, 4);
            $a['fc_aff']       = round($a['fc_aff']   / $n, 4);
            $a['fc_split']     = round($a['fc_split'] / $n, 4);
            $a['fc_mov']       = round($a['fc_mov']   / $n, 4);
            $a['split']        = count($a['placements']) > 1;
            $a['violation']    = false;
            $a['total_time']   = round(array_sum(array_column($a['placements'], 'time')), 4);
        }
        unset($a);

        return $this->aggregate($assignments);
    }

    // ─── Fitness calculation for S1 / S2 ─────────────────────────────────────

    /**
     * Hitung fitness score untuk assignments S1/S2 menggunakan rumus identik dengan
     * GA engine Python (fitness.py): FC_CAP + FC_CAT + FC_AFF + FC_SPLIT + FC_MOV = 100
     */
    private function calcSimFitness(
        array $assignments,
        array $cellsById,
        array $existingCells
    ): array {
        // Pra-hitung total demand per cell (untuk FC_CAP)
        $cellDemand = [];
        foreach ($assignments as $a) {
            foreach ($a['placements'] as $p) {
                $cellDemand[$p['cell_id']] = ($cellDemand[$p['cell_id']] ?? 0) + $p['points'];
            }
        }

        // Pra-hitung semua lokasi per item_id dalam assignments ini (untuk FC_SPLIT)
        $itemLocations = [];   // item_id → [cell_id, ...]
        foreach ($assignments as $a) {
            foreach ($a['placements'] as $p) {
                $itemLocations[$a['item_id']][] = $p['cell_id'];
            }
        }

        $fitnesses = [];

        foreach ($assignments as $did => $a) {
            $item = $a;  // item fields are inside assignment
            $geneFits = [];

            foreach ($a['placements'] as $p) {
                $cell = $cellsById[$p['cell_id']] ?? null;
                if (!$cell) {
                    $geneFits[] = 0.0;
                    continue;
                }

                $fcCap   = $this->fcCap($p['cell_id'], $p['points'], $cellDemand, $cell);
                $fcCat   = $this->fcCat($item, $cell);
                $fcAff   = $this->fcAff($item, $p['cell_id'], $existingCells, $cellsById);
                $fcSplit = $this->fcSplit($item['item_id'], $p['cell_id'], $itemLocations, $existingCells, $cellsById);
                $fcMov   = $this->fcMov($item['movement_type'] ?? null, $cell['blok'] ?? null);

                $geneFits[] = $fcCap + $fcCat + $fcAff + $fcSplit + $fcMov;
            }

            $fitnesses[$did] = count($geneFits) > 0
                ? round(array_sum($geneFits) / count($geneFits), 4)
                : 0.0;
        }

        return $fitnesses;
    }

    // FC_CAP (max 30) — identik dengan Python fc_capacity()
    private function fcCap(int $cellId, int $myPoints, array $cellDemand, array $cell): float
    {
        $demand    = $cellDemand[$cellId] ?? $myPoints;
        $remaining = max(0, (int) $cell['capacity_remaining']);
        if ($demand <= $remaining) return 30.0;
        return max(0.0, round(30.0 * $remaining / $demand, 6));
    }

    // FC_CAT (max 25) — disederhanakan dari Python fc_category()
    private function fcCat(array $item, array $cell): float
    {
        $itemCat = $item['category_id'] ?? null;
        $cellCat = $cell['dominant_category_id'] ?? null;

        if ($cellCat === null) return 12.5;                   // cell kosong/baru
        if ($itemCat !== null && $itemCat == $cellCat) return 25.0;  // perfect match
        return 0.0;                                            // mismatch
    }

    // FC_AFF (max 20) — disederhanakan dari Python fc_affinity() (continuity only)
    private function fcAff(
        array $item,
        int $cellId,
        array $existingCells,
        array $cellsById
    ): float {
        $itemId   = $item['item_id'];
        $existing = $existingCells[$itemId] ?? [];

        if (in_array($cellId, $existing)) return 20.0;  // item sudah ada di cell ini
        if (empty($existing))             return 10.0;  // item baru, belum ada histori

        // Item punya histori di cell lain → skor berdasarkan jarak ke cell terdekat
        $minDist = PHP_FLOAT_MAX;
        foreach ($existing as $existCellId) {
            $d = $this->cellDist($cellsById[$cellId] ?? null, $cellsById[$existCellId] ?? null);
            if ($d < $minDist) $minDist = $d;
        }

        if ($minDist <= 0)  return 20.0;
        if ($minDist <= 1)  return 19.0;
        if ($minDist <= 5)  return 16.0;
        if ($minDist <= 10) return 8.0;
        return 0.0;
    }

    // FC_SPLIT (max 15) — identik dengan Python fc_split()
    private function fcSplit(
        int $itemId,
        int $cellId,
        array $itemLocations,
        array $existingCells,
        array $cellsById
    ): float {
        $recommended = array_unique($itemLocations[$itemId] ?? []);
        $existing    = array_unique($existingCells[$itemId] ?? []);
        $allCells    = array_unique(array_merge($recommended, $existing));
        $locCount    = count($allCells);

        // FC_SPLIT_COUNT (max 7.5)
        $splitCount = $locCount <= 1 ? 7.5 : max(0.0, round(7.5 / $locCount, 6));

        // FC_SPLIT_DISTANCE (max 7.5)
        $refs = array_diff($allCells, [$cellId]);
        if (empty($refs)) {
            $distScore = 7.5;
        } else {
            $currCell = $cellsById[$cellId] ?? null;
            $minDist  = PHP_FLOAT_MAX;
            foreach ($refs as $ref) {
                $d = $this->cellDist($currCell, $cellsById[$ref] ?? null);
                if ($d < $minDist) $minDist = $d;
            }
            if ($minDist <= 1)  $distScore = 7.5;
            elseif ($minDist <= 5)  $distScore = 5.0;
            elseif ($minDist <= 10) $distScore = 3.0;
            else                    $distScore = 0.0;
        }

        return round($splitCount + $distScore, 6);
    }

    // FC_MOV (max 10) — identik dengan Python fc_movement()
    private function fcMov(?string $movType, int|string|null $blok): float
    {
        if ($blok === null || $movType === null) return 10.0;
        $b = (int) $blok;

        if ($movType === 'fast_moving') {
            if ($b <= 1) return 10.0;
            if ($b <= 2) return 8.0;
            if ($b <= 3) return 5.0;
            return 2.0;
        }
        if ($movType === 'slow_moving') {
            if ($b >= 4) return 10.0;
            if ($b >= 3) return 8.0;
            if ($b >= 2) return 6.0;
            return 3.0;
        }
        // non_moving
        if ($b >= 5) return 10.0;
        if ($b >= 4) return 7.0;
        if ($b >= 3) return 4.0;
        return 1.0;
    }

    // Cell distance — identik dengan Python cell_distance()
    private function cellDist(?array $a, ?array $b): float
    {
        if (!$a || !$b) return 9999.0;
        if ($a['blok'] !== null && $b['blok'] !== null) {
            $ga = is_numeric($a['grup']) ? (int)$a['grup'] : (ord(strtoupper((string)($a['grup'] ?? 'A'))) - ord('A') + 1);
            $gb = is_numeric($b['grup']) ? (int)$b['grup'] : (ord(strtoupper((string)($b['grup'] ?? 'A'))) - ord('A') + 1);
            return abs((int)$a['blok'] - (int)$b['blok']) * 10
                 + abs($ga - $gb) * 3
                 + abs((int)($a['kolom'] ?? 1) - (int)($b['kolom'] ?? 1)) * 2
                 + abs((int)($a['baris'] ?? 1) - (int)($b['baris'] ?? 1)) * 0.5;
        }
        return 9999.0;
    }

    // ─── Aggregate metrics ───────────────────────────────────────────────────

    private function aggregate(array $assignments, array $fitnesses = []): array
    {
        $splits     = 0;
        $violations = 0;
        $totalTime  = 0;
        $totalLocs  = 0;
        $fitnessSum = 0;

        foreach ($assignments as $did => &$a) {
            if ($a['split'])     $splits++;
            if ($a['violation']) $violations++;
            $totalTime  += $a['total_time'];
            $totalLocs  += max(1, count($a['placements']));

            // Attach per-item fitness so writeDetail can access $a['_fitness']
            if (!isset($a['gene_fitness']) && isset($fitnesses[$did])) {
                $a['_fitness'] = (float) $fitnesses[$did];
            }

            $fitnessSum += isset($a['gene_fitness'])
                ? (float) $a['gene_fitness']
                : (float) ($fitnesses[$did] ?? 0);
        }
        unset($a);

        $n = count($assignments);

        return [
            'split_count'     => $splits,
            'violation_count' => $violations,
            'avg_locations'   => $n > 0 ? round($totalLocs / $n, 4) : 0,
            'avg_time'        => $n > 0 ? round($totalTime / $n, 4) : 0,
            'total_time'      => round($totalTime, 2),
            'avg_fitness'     => $n > 0 ? round($fitnessSum / $n, 4) : 0,
            'item_count'      => $n,
            'assignments'     => $assignments,
            'fitnesses'       => $fitnesses,
        ];
    }

    // ─── CSV writers ─────────────────────────────────────────────────────────

    private function writeComparison(string $dir, array $s1, array $s2, array $s3): void
    {
        // lower-is-better improvement
        $pctLow  = fn($base, $ga) => $base > 0
            ? round(($base - $ga) / $base * 100, 2) . '%' : '-';
        // higher-is-better improvement
        $pctHigh = fn($base, $ga) => $base > 0
            ? round(($ga - $base) / $base * 100, 2) . '%' : '-';

        $fp = fopen("{$dir}/scenario_comparison.csv", 'w');
        fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($fp, [
            'Metrik Evaluasi',
            'S1 Manual/Existing',
            'S2 Random Placement',
            'S3 Genetic Algorithm',
            'Perbaikan GA vs S1 (%)',
            'Perbaikan GA vs S2 (%)',
        ]);

        $rows = [
            ['Jumlah Item Diproses',
                $s1['item_count'], $s2['item_count'], $s3['item_count'], '-', '-'],

            ['Jumlah SKU Split Location',
                $s1['split_count'], $s2['split_count'], $s3['split_count'],
                $pctLow($s1['split_count'] ?: 1e-9, $s3['split_count']),
                $pctLow($s2['split_count'] ?: 1e-9, $s3['split_count'])],

            ['Rata-rata Lokasi per Item',
                $s1['avg_locations'], $s2['avg_locations'], $s3['avg_locations'],
                $pctLow($s1['avg_locations'], $s3['avg_locations']),
                $pctLow($s2['avg_locations'], $s3['avg_locations'])],

            ['Pelanggaran Kapasitas',
                $s1['violation_count'], $s2['violation_count'], $s3['violation_count'],
                $pctLow($s1['violation_count'] ?: 1e-9, $s3['violation_count']),
                $pctLow($s2['violation_count'] ?: 1e-9, $s3['violation_count'])],

            ['Estimasi Total Waktu Put-away (menit)',
                $s1['total_time'], $s2['total_time'], $s3['total_time'],
                $pctLow($s1['total_time'], $s3['total_time']),
                $pctLow($s2['total_time'], $s3['total_time'])],

            ['Estimasi Rata-rata Waktu per Item (menit)',
                $s1['avg_time'], $s2['avg_time'], $s3['avg_time'],
                $pctLow($s1['avg_time'], $s3['avg_time']),
                $pctLow($s2['avg_time'], $s3['avg_time'])],

            ['Rata-rata Fitness Score (/100)',
                $s1['avg_fitness'], $s2['avg_fitness'], $s3['avg_fitness'],
                $pctHigh($s1['avg_fitness'] ?: 1e-9, $s3['avg_fitness']),
                $pctHigh($s2['avg_fitness'] ?: 1e-9, $s3['avg_fitness'])],
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
            'Detail ID', 'DO Number', 'SKU', 'Nama Item', 'Kategori', 'Qty',
            'S1 Sel', 'S1 Lokasi', 'S1 Split', 'S1 Waktu (mnt)', 'S1 Fitness',
            'S2 Sel', 'S2 Lokasi', 'S2 Split', 'S2 Waktu (mnt)', 'S2 Fitness',
            'S3 Sel (GA)', 'S3 Lokasi', 'S3 Split', 'S3 Waktu (mnt)', 'S3 Fitness (GA)',
            'Selisih Waktu S1 vs GA', 'Selisih Waktu S2 vs GA',
            'Selisih Fitness GA vs S1', 'Selisih Fitness GA vs S2',
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
            $s1f = $s1['_fitness'] ?? null;
            $s2f = $s2['_fitness'] ?? null;
            $s3f = ($s3['gene_fitness'] ?? null);

            fputcsv($fp, [
                $did,
                $base['do_number']         ?? '-',
                $base['sku']               ?? '-',
                $base['item_name']         ?? '-',
                $base['category']          ?? '-',
                $base['quantity_received'] ?? $base['qty'] ?? '-',
                // S1
                $s1 ? implode(';', array_column($s1['placements'], 'cell_code')) : '-',
                $s1 ? count($s1['placements']) : '-',
                $s1 ? ($s1['split'] ? 'Ya' : 'Tidak') : '-',
                $s1t ?? '-',
                $s1f !== null ? round($s1f, 4) : '-',
                // S2
                $s2 ? implode(';', array_column($s2['placements'], 'cell_code')) : '-',
                $s2 ? count($s2['placements']) : '-',
                $s2 ? ($s2['split'] ? 'Ya' : 'Tidak') : '-',
                $s2t ?? '-',
                $s2f !== null ? round($s2f, 4) : '-',
                // S3 GA
                $s3 ? implode(';', array_column($s3['placements'], 'cell_code')) : '-',
                $s3 ? count($s3['placements']) : '-',
                $s3 ? ($s3['split'] ? 'Ya' : 'Tidak') : '-',
                $s3t ?? '-',
                $s3f !== null ? round($s3f, 4) : '-',
                // Selisih
                ($s1t !== null && $s3t !== null) ? round($s1t - $s3t, 4) : '-',
                ($s2t !== null && $s3t !== null) ? round($s2t - $s3t, 4) : '-',
                ($s1f !== null && $s3f !== null) ? round($s3f - $s1f, 4) : '-',
                ($s2f !== null && $s3f !== null) ? round($s3f - $s2f, 4) : '-',
            ]);
        }

        fclose($fp);
        $this->line('  ✓ scenario_detail.csv');
    }

    private function printSummary(array $s1, array $s2, array $s3): void
    {
        $this->line('');
        $this->info('=== RINGKASAN PERBANDINGAN 3 SKENARIO ===');
        $this->table(
            ['Metrik', 'S1 Manual', 'S2 Random', 'S3 GA'],
            [
                ['Item Diproses',       $s1['item_count'],      $s2['item_count'],      $s3['item_count']],
                ['Split Location',      $s1['split_count'],     $s2['split_count'],     $s3['split_count']],
                ['Avg Lokasi/Item',     $s1['avg_locations'],   $s2['avg_locations'],   $s3['avg_locations']],
                ['Kapasitas Violation', $s1['violation_count'], $s2['violation_count'], $s3['violation_count']],
                ['Avg Waktu/Item(mnt)', $s1['avg_time'],        $s2['avg_time'],        $s3['avg_time']],
                ['Avg Fitness Score',   $s1['avg_fitness'],     $s2['avg_fitness'],     $s3['avg_fitness']],
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
            'item_id'      => $item['item_id'],
            'sku'          => $item['sku'],
            'item_name'    => $item['item_name'],
            'category'     => $item['category'],
            'category_id'  => $item['category_id'],
            'movement_type'=> $item['movement_type'] ?? null,
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

    private function catMatch(?int $cellCatId, ?int $itemCatId): int
    {
        if ($cellCatId === null) return 0;
        return ($itemCatId !== null && $itemCatId == $cellCatId) ? 1 : 0;
    }

    private function dist(array $cell): float
    {
        $b = is_numeric($cell['blok']) ? (int) $cell['blok'] : max(1, ord(strtoupper((string) ($cell['blok'] ?? 'A'))) - ord('A') + 1);
        $g = is_numeric($cell['grup']) ? (int) $cell['grup'] : max(1, ord(strtolower((string) ($cell['grup'] ?? 'a'))) - ord('a') + 1);
        $k = max(1, (int) ($cell['kolom'] ?? 1));
        return ($b * self::BLOK_M) + ($g * self::GRUP_M) + ($k * self::KOLOM_M);
    }
}
