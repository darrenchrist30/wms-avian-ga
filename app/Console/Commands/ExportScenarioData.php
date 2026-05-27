<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportScenarioData extends Command
{
    protected $signature   = 'wms:export-scenario-data';
    protected $description = 'Export data untuk pengujian 3 skenario GA ke file CSV';

    public function handle(): int
    {
        $dir = storage_path('app/exports');
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $this->info('Mengekspor data skenario...');

        $this->exportGaFitness($dir);
        $this->exportStockPositions($dir);
        $this->exportCoOccurrence($dir);
        $this->exportCapacityUtilization($dir);
        $this->exportExistingPositions($dir);
        $this->exportRandomPositions($dir);
        $this->exportMspartValidation($dir);

        $this->info('');
        $this->info('✓ Selesai! File tersimpan di:');
        $this->line("  {$dir}/");
        $this->line('  1. ga_fitness_scores.csv');
        $this->line('  2. stock_positions.csv');
        $this->line('  3. co_occurrence.csv');
        $this->line('  4. capacity_utilization.csv');
        $this->line('  5. existing_positions.csv  (Skenario 1 – kondisi existing perusahaan)');
        $this->line('  6. random_positions.csv    (Skenario 2 – posisi diacak, dari mspart_random)');
        $this->line('  7. mspart_validation.csv   (Validasi: posisi real mspart vs rekomendasi GA)');

        return self::SUCCESS;
    }

    private function exportGaFitness(string $dir): void
    {
        $this->line('  → ga_fitness_scores.csv');

        $rows = DB::table('ga_recommendations as ga')
            ->join('inbound_transactions as io', 'io.id', '=', 'ga.inbound_order_id')
            ->select([
                'io.do_number',
                'io.status as order_status',
                'ga.fitness_score',
                'ga.generations_run',
                'ga.execution_time_ms',
                'ga.created_at',
            ])
            ->orderBy('ga.created_at')
            ->get();

        $this->writeCsv("{$dir}/ga_fitness_scores.csv", [
            'DO Number', 'Status Order', 'Fitness Score', 'Generasi Berjalan', 'Waktu Eksekusi (ms)', 'Tanggal Run',
        ], $rows->map(fn($r) => [
            $r->do_number,
            $r->order_status,
            $r->fitness_score,
            $r->generations_run ?? 0,
            $r->execution_time_ms ?? 0,
            $r->created_at,
        ])->toArray());
    }

    private function exportStockPositions(string $dir): void
    {
        $this->line('  → stock_positions.csv');

        $rows = DB::table('stock_records as sr')
            ->join('items as i', 'i.id', '=', 'sr.item_id')
            ->join('item_categories as cat', 'cat.id', '=', 'i.category_id')
            ->join('cells as c', 'c.id', '=', 'sr.cell_id')
            ->leftJoin('racks as r', 'r.id', '=', 'c.rack_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'sr.warehouse_id')
            ->where('sr.status', 'available')
            ->where('sr.quantity', '>', 0)
            ->select([
                'i.sku',
                'i.name as item_name',
                'cat.name as category',
                'sr.quantity',
                'c.code as cell_code',
                DB::raw("CONCAT(c.blok,'-',UPPER(c.grup),'-',c.kolom,'-',c.baris) as physical_code"),
                'c.blok', 'c.grup', 'c.kolom', 'c.baris',
                'r.code as rack_code',
                'w.name as warehouse',
                'sr.inbound_date',
                DB::raw('DATEDIFF(NOW(), sr.inbound_date) as days_in_storage'),
            ])
            ->orderBy('w.name')->orderBy('c.blok')->orderBy('c.grup')
            ->orderBy('c.kolom')->orderBy('c.baris')
            ->get();

        $this->writeCsv("{$dir}/stock_positions.csv", [
            'SKU', 'Nama Item', 'Kategori', 'Qty', 'Cell Code', 'Physical Code',
            'Blok', 'Grup', 'Kolom', 'Baris', 'Rak', 'Gudang', 'Tgl Masuk', 'Hari di Gudang',
        ], $rows->map(fn($r) => [
            $r->sku, $r->item_name, $r->category, $r->quantity,
            $r->cell_code, $r->physical_code,
            $r->blok, $r->grup, $r->kolom, $r->baris,
            $r->rack_code, $r->warehouse,
            $r->inbound_date, $r->days_in_storage,
        ])->toArray());
    }

    private function exportCoOccurrence(string $dir): void
    {
        $this->line('  → co_occurrence.csv');

        $rows = DB::table('item_affinities as ia')
            ->join('items as a', 'a.id', '=', 'ia.item_id')
            ->join('items as b', 'b.id', '=', 'ia.related_item_id')
            ->leftJoin('item_categories as ca', 'ca.id', '=', 'a.category_id')
            ->leftJoin('item_categories as cb', 'cb.id', '=', 'b.category_id')
            ->select([
                'a.sku as sku_a', 'a.name as item_a', 'ca.name as category_a',
                'b.sku as sku_b', 'b.name as item_b', 'cb.name as category_b',
                'ia.co_occurrence_count', 'ia.affinity_score',
            ])
            ->orderByDesc('ia.co_occurrence_count')
            ->get();

        $this->writeCsv("{$dir}/co_occurrence.csv", [
            'SKU A', 'Item A', 'Kategori A', 'SKU B', 'Item B', 'Kategori B',
            'Co-occurrence Count', 'Affinity Score',
        ], $rows->map(fn($r) => [
            $r->sku_a, $r->item_a, $r->category_a,
            $r->sku_b, $r->item_b, $r->category_b,
            $r->co_occurrence_count, $r->affinity_score,
        ])->toArray());
    }

    private function exportCapacityUtilization(string $dir): void
    {
        $this->line('  → capacity_utilization.csv');

        $rows = DB::table('cells as c')
            ->leftJoin('racks as r', 'r.id', '=', 'c.rack_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->select([
                DB::raw("CONCAT(c.blok,'-',UPPER(c.grup),'-',c.kolom,'-',c.baris) as physical_code"),
                'c.code as cell_code',
                'r.code as rack_code',
                'w.name as warehouse',
                'c.capacity_max',
                'c.capacity_used',
                DB::raw('GREATEST(0, CAST(c.capacity_max AS SIGNED) - CAST(c.capacity_used AS SIGNED)) as capacity_remaining'),
                DB::raw('CASE WHEN c.capacity_max > 0 THEN ROUND(c.capacity_used / c.capacity_max * 100, 1) ELSE 0 END as utilization_pct'),
                'c.status as cell_status',
            ])
            ->where('c.is_active', true)
            ->orderBy('w.name')->orderBy('r.code')->orderBy('c.blok')->orderBy('c.grup')
            ->get();

        $this->writeCsv("{$dir}/capacity_utilization.csv", [
            'Physical Code', 'Cell Code', 'Rak', 'Gudang',
            'Kapasitas Maks (poin)', 'Terpakai (poin)', 'Sisa (poin)', 'Utilisasi (%)', 'Status Cell',
        ], $rows->map(fn($r) => [
            $r->physical_code, $r->cell_code, $r->rack_code, $r->warehouse,
            $r->capacity_max, $r->capacity_used, $r->capacity_remaining,
            $r->utilization_pct, $r->cell_status,
        ])->toArray());
    }

    private function exportExistingPositions(string $dir): void
    {
        $this->line('  → existing_positions.csv');

        $rows = DB::table('mspart')
            ->whereNotNull('blok')
            ->where('blok', '!=', '')
            ->where('blok', '!=', '0')
            ->select('kode', 'nama', 'stok', 'sat', 'blok', 'grup', 'kolom', 'baris')
            ->orderBy('blok')->orderBy('grup')->orderBy('kolom')->orderBy('baris')
            ->get();

        $this->writeCsv("{$dir}/existing_positions.csv", [
            'Kode', 'Nama', 'Stok', 'Satuan', 'Blok', 'Grup', 'Kolom', 'Baris',
        ], $rows->map(fn($r) => [
            $r->kode, $r->nama, $r->stok, $r->sat,
            $r->blok, $r->grup, $r->kolom, $r->baris,
        ])->toArray());
    }

    private function exportRandomPositions(string $dir): void
    {
        $this->line('  → random_positions.csv');

        $tableExists = DB::select("SHOW TABLES LIKE 'mspart_random'");
        if (empty($tableExists)) {
            $this->warn('  ! Tabel mspart_random belum ada. Jalankan: php artisan wms:create-random-scenario');
            return;
        }

        $rows = DB::table('mspart_random')
            ->whereNotNull('blok')
            ->where('blok', '!=', '')
            ->where('blok', '!=', '0')
            ->select('kode', 'nama', 'stok', 'sat', 'blok', 'grup', 'kolom', 'baris')
            ->orderBy('blok')->orderBy('grup')->orderBy('kolom')->orderBy('baris')
            ->get();

        $this->writeCsv("{$dir}/random_positions.csv", [
            'Kode', 'Nama', 'Stok', 'Satuan', 'Blok', 'Grup', 'Kolom', 'Baris',
        ], $rows->map(fn($r) => [
            $r->kode, $r->nama, $r->stok, $r->sat,
            $r->blok, $r->grup, $r->kolom, $r->baris,
        ])->toArray());
    }

    private function exportMspartValidation(string $dir): void
    {
        $this->line('  → mspart_validation.csv');

        // Item dari DO real yang kode-nya ada di mspart dengan posisi valid
        $rows = DB::table('inbound_transactions as io')
            ->join('inbound_details as id',            'id.inbound_order_id',      '=', 'io.id')
            ->join('items as i',                       'i.id',                     '=', 'id.item_id')
            ->join('item_categories as cat',           'cat.id',                   '=', 'i.category_id')
            ->join('mspart as m',                      DB::raw('m.kode COLLATE utf8mb4_0900_ai_ci'), '=', 'i.sku')
            ->join('ga_recommendations as gr',         'gr.inbound_order_id',      '=', 'io.id')
            ->join('ga_recommendation_details as grd', 'grd.ga_recommendation_id', '=', 'gr.id')
            ->join('cells as c',                       'c.id',                     '=', 'grd.cell_id')
            ->where('io.do_number',              'like', 'SJ/GUD-AAP/%')
            ->where('grd.inbound_order_item_id', '=',   DB::raw('id.id'))
            ->whereNotNull('m.blok')
            ->where('m.blok', '!=', '')
            ->where('m.blok', '!=', '0')
            ->select(
                'io.do_number',
                'i.sku',
                'i.name as item_name',
                'cat.name as category',
                'id.quantity_received',
                // Posisi real mspart (S1 aktual)
                'm.blok as m_blok', 'm.grup as m_grup',
                'm.kolom as m_kolom', 'm.baris as m_baris',
                // Posisi GA (S3 aktual)
                'c.code as ga_cell',
                'c.blok as ga_blok', 'c.grup as ga_grup',
                'c.kolom as ga_kolom', 'c.baris as ga_baris',
                // Fitness GA
                'grd.gene_fitness',
                'grd.fc_cap_score', 'grd.fc_cat_score',
                'grd.fc_aff_score', 'grd.fc_split_score', 'grd.fc_mov_score',
            )
            ->orderBy('i.sku')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('  ! Tidak ada item yang cocok antara mspart dan DO real.');
            return;
        }

        $data = $rows->map(function ($r) {
            // Hitung jarak Manhattan dari posisi mspart (blok huruf → angka)
            $mBlok  = is_numeric($r->m_blok)  ? (int)$r->m_blok  : max(1, ord(strtoupper($r->m_blok))  - ord('A') + 1);
            $mGrup  = is_numeric($r->m_grup)  ? (int)$r->m_grup  : max(1, ord(strtolower($r->m_grup))  - ord('a') + 1);
            $mKolom = is_numeric($r->m_kolom) ? (int)$r->m_kolom : 1;
            $mBaris = is_numeric($r->m_baris) ? (int)$r->m_baris : 1;
            $mDist  = ($mBlok * 10) + ($mGrup * 5) + ($mKolom * 2);
            $mWaktu = round($mDist / 50 + $mBaris * 0.5 + 2, 4);

            // Hitung jarak Manhattan dari posisi GA (cell, blok angka)
            $gBlok  = $r->ga_blok  !== null ? (int)$r->ga_blok  : 1;
            $gGrup  = $r->ga_grup  !== null ? (is_numeric($r->ga_grup) ? (int)$r->ga_grup : max(1, ord(strtolower($r->ga_grup)) - ord('a') + 1)) : 1;
            $gKolom = $r->ga_kolom !== null ? (int)$r->ga_kolom : 1;
            $gBaris = $r->ga_baris !== null ? (int)$r->ga_baris : 1;
            $gDist  = ($gBlok * 10) + ($gGrup * 5) + ($gKolom * 2);
            $gWaktu = round($gDist / 50 + $gBaris * 0.5 + 2, 4);

            $selisih = round($mWaktu - $gWaktu, 4);

            return [
                $r->do_number,
                $r->sku,
                $r->item_name,
                $r->category,
                $r->quantity_received,
                // S1 mspart
                "{$r->m_blok}-{$r->m_grup}-{$r->m_kolom}-{$r->m_baris}",
                $mDist,
                $mWaktu,
                // S3 GA
                $r->ga_cell,
                "{$r->ga_blok}-{$r->ga_grup}-{$r->ga_kolom}-{$r->ga_baris}",
                $gDist,
                $gWaktu,
                // Fitness GA
                round($r->gene_fitness, 4),
                round($r->fc_cap_score,   4),
                round($r->fc_cat_score,   4),
                round($r->fc_aff_score,   4),
                round($r->fc_split_score, 4),
                round($r->fc_mov_score,   4),
                // Perbandingan
                $selisih,
                $selisih > 0 ? 'GA Lebih Efisien' : ($selisih < 0 ? 'Mspart Lebih Dekat' : 'Sama'),
            ];
        })->toArray();

        $this->writeCsv("{$dir}/mspart_validation.csv", [
            'DO Number', 'SKU', 'Nama Item', 'Kategori', 'Qty Diterima',
            // S1
            'S1 Posisi Mspart', 'S1 Jarak (unit)', 'S1 Est. Waktu (mnt)',
            // S3
            'S3 Cell GA', 'S3 Posisi GA', 'S3 Jarak (unit)', 'S3 Est. Waktu (mnt)',
            // Fitness
            'Gene Fitness GA', 'FC_CAP', 'FC_CAT', 'FC_AFF', 'FC_SPLIT', 'FC_MOV',
            // Perbandingan
            'Selisih Waktu S1 vs GA (mnt)', 'Keterangan',
        ], $data);

        $this->line('  ✓ ' . count($data) . ' item tervalidasi dari data mspart real.');
    }

    private function writeCsv(string $path, array $header, array $rows): void
    {
        $fp = fopen($path, 'w');
        fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM agar Excel baca benar
        fputcsv($fp, $header);
        foreach ($rows as $row) fputcsv($fp, $row);
        fclose($fp);
    }
}
