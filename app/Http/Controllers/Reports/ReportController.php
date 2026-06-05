<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\GaRecommendation;
use App\Models\InboundOrder;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    // ─── 1. Laporan Inventaris (Stok) ───────────────────────────────────────
    public function inventory(Request $request)
    {
        $deadstockDays = max(1, (int) $request->input('deadstock_days', 90));

        // Stok per kategori (pie chart)
        $stockByCategory = DB::table('stock_records as sr')
            ->join('items', 'items.id', '=', 'sr.item_id')
            ->leftJoin('item_categories as cat', 'cat.id', '=', 'items.category_id')
            ->where('sr.status', 'available')
            ->groupBy('cat.id', 'cat.name', 'cat.color_code')
            ->select([
                DB::raw("COALESCE(cat.name, 'Tanpa Kategori') as name"),
                DB::raw("COALESCE(cat.color_code, '#6c757d') as color"),
                DB::raw('SUM(sr.quantity) as total_qty'),
                DB::raw('COUNT(DISTINCT sr.item_id) as sku_count'),
            ])
            ->orderByDesc('total_qty')
            ->get();

        // Top 10 item by stok
        $topItems = DB::table('stock_records as sr')
            ->join('items', 'items.id', '=', 'sr.item_id')
            ->where('sr.status', 'available')
            ->groupBy('items.id', 'items.name', 'items.sku')
            ->select([
                'items.name',
                'items.sku',
                DB::raw('SUM(sr.quantity) as total_qty'),
            ])
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        // Utilization gudang — hitung kolom-level (baris IS NULL) sebagai satuan cell.
        // "Terpakai" = kolom yang punya minimal 1 baris-level anak berisi stok.
        $warehouseUtil = DB::table('warehouses as w')
            ->leftJoin('racks as r', function ($join) {
                $join->on('r.warehouse_id', '=', 'w.id')->whereNull('r.deleted_at');
            })
            ->leftJoin('cells as c', function ($join) {
                $join->on('c.rack_id', '=', 'r.id')->whereNull('c.baris'); // kolom-level saja
            })
            ->leftJoin('cells as cb', function ($join) {
                // baris-level children dari kolom yang sama
                $join->on('cb.rack_id', '=', 'c.rack_id')
                     ->on('cb.blok',    '=', 'c.blok')
                     ->on('cb.grup',    '=', 'c.grup')
                     ->on('cb.kolom',   '=', 'c.kolom')
                     ->whereNotNull('cb.baris');
            })
            ->leftJoin(DB::raw('(SELECT cell_id FROM stock_records WHERE status="available" GROUP BY cell_id HAVING SUM(quantity) > 0) as stk'), 'stk.cell_id', '=', 'cb.id')
            ->where('w.is_active', true)
            ->groupBy('w.id', 'w.name')
            ->select([
                'w.name as warehouse',
                DB::raw('COUNT(DISTINCT c.id) as total_cells'),
                DB::raw('COUNT(DISTINCT CASE WHEN stk.cell_id IS NOT NULL THEN c.id END) as used_cells'),
            ])
            ->get()
            ->map(function ($row) {
                $row->utilization = $row->total_cells > 0
                    ? round(($row->used_cells / $row->total_cells) * 100, 1)
                    : 0;
                return $row;
            });

        // Summary angka
        $summary = [
            'total_skus'   => Stock::where('status', 'available')->distinct('item_id')->count('item_id'),
            'total_qty'    => Stock::where('status', 'available')->sum('quantity'),
            'below_min'    => $this->countBelowMin(),
            'deadstock'    => Stock::deadstock($deadstockDays)->distinct('item_id')->count('item_id'),
            'deadstock_days' => $deadstockDays,
        ];

        return view('reports.inventory', compact('stockByCategory', 'topItems', 'warehouseUtil', 'summary'));
    }

    // ─── 2. Laporan Penerimaan (Inbound) ────────────────────────────────────
    public function inbound(Request $request)
    {
        $year = $request->input('year', now()->year);

        // Trend inbound per bulan (tahun ini)
        $monthlyTrend = DB::table('inbound_transactions')
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as total_orders, SUM(0) as total_items')
            ->whereYear('created_at', $year)
            ->groupByRaw('MONTH(created_at)')
            ->orderByRaw('MONTH(created_at)')
            ->get()
            ->keyBy('month');

        // Qty penerimaan per bulan (dari inbound_details)
        $monthlyQty = DB::table('inbound_details as id')
            ->join('inbound_transactions as it', 'it.id', '=', 'id.inbound_order_id')
            ->selectRaw('MONTH(it.created_at) as month, SUM(id.quantity_received) as total_qty, COUNT(DISTINCT it.id) as order_count')
            ->whereYear('it.created_at', $year)
            ->groupByRaw('MONTH(it.created_at)')
            ->orderByRaw('MONTH(it.created_at)')
            ->get()
            ->keyBy('month');

        // Distribusi status (dengan label yang bersih)
        $statusDist = DB::table('inbound_transactions')
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get()
            ->map(function ($row) {
                $row->label = ucwords(str_replace('_', ' ', $row->status));
                return $row;
            });

        // Rata-rata waktu proses DO (jam) per bulan — hanya order completed
        $avgProcessingTime = DB::table('inbound_transactions')
            ->selectRaw('MONTH(processed_at) as month, ROUND(AVG(TIMESTAMPDIFF(HOUR, created_at, processed_at)), 1) as avg_hours')
            ->whereYear('created_at', $year)
            ->where('status', 'completed')
            ->whereNotNull('processed_at')
            ->groupByRaw('MONTH(processed_at)')
            ->orderByRaw('MONTH(processed_at)')
            ->get()
            ->keyBy('month');

        // Top 5 SKU terbanyak diterima (dari inbound_details)
        $topSkus = DB::table('inbound_details as id')
            ->join('inbound_transactions as it', 'it.id', '=', 'id.inbound_order_id')
            ->join('items', 'items.id', '=', 'id.item_id')
            ->selectRaw('items.sku, items.name, SUM(id.quantity_received) as total_qty')
            ->whereYear('it.created_at', $year)
            ->groupBy('items.id', 'items.sku', 'items.name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        // Available years
        $years = DB::table('inbound_transactions')
            ->selectRaw('YEAR(created_at) as yr')
            ->groupByRaw('YEAR(created_at)')
            ->orderByDesc('yr')
            ->pluck('yr');

        // Summary
        $summary = [
            'total_orders'    => InboundOrder::whereYear('created_at', $year)->count(),
            'received_orders' => InboundOrder::whereYear('created_at', $year)->whereIn('status', ['recommended','put_away'])->count(),
            'processed_orders'=> InboundOrder::whereYear('created_at', $year)->where('status', 'completed')->count(),
            'total_warehouses' => Warehouse::where('is_active', true)->count(),
        ];

        // Build 12-month arrays for chart
        $months              = range(1, 12);
        $chartOrders         = [];
        $chartQty            = [];
        $chartAvgProcessing  = [];
        foreach ($months as $m) {
            $chartOrders[]        = $monthlyQty[$m]->order_count ?? 0;
            $chartQty[]           = $monthlyQty[$m]->total_qty ?? 0;
            $chartAvgProcessing[] = isset($avgProcessingTime[$m]) ? (float) $avgProcessingTime[$m]->avg_hours : null;
        }

        return view('reports.inbound', compact(
            'summary', 'statusDist',
            'chartOrders', 'chartQty', 'chartAvgProcessing',
            'topSkus', 'year', 'years'
        ));
    }

    // ─── 3. Laporan Put-Away ─────────────────────────────────────────────────
    public function putaway(Request $request)
    {
        $year       = $request->input('year', now()->year);
        $dateFrom   = $request->input('date_from') ?: null;
        $dateTo     = $request->input('date_to')   ?: $dateFrom;
        $operatorId = $request->input('operator_id');
        $hasFilter  = $dateFrom && $dateTo;

        // Put-away per bulan — selalu per tahun (tren overview, tidak ikut filter tanggal)
        $monthlyPutAway = DB::table('stock_records')
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as records, SUM(quantity) as total_qty')
            ->whereYear('created_at', $year)
            ->where('status', 'available')
            ->groupByRaw('MONTH(created_at)')
            ->orderByRaw('MONTH(created_at)')
            ->get()
            ->keyBy('month');

        // GA recommendation stats — selalu per tahun
        $gaStats = DB::table('ga_recommendations')
            ->selectRaw('
                COUNT(*) as total,
                AVG(fitness_score) as avg_fitness,
                AVG(execution_time_ms) as avg_exec_ms,
                AVG(generations_run) as avg_generations,
                SUM(CASE WHEN status = "accepted" THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected
            ')
            ->whereYear('created_at', $year)
            ->first();

        // Distribusi aktivitas put-away per kategori — dari put_away_confirmations
        $byCategory = DB::table('put_away_confirmations as pac')
            ->join('inbound_details as idt', 'idt.id', '=', 'pac.inbound_order_item_id')
            ->join('items', 'items.id', '=', 'idt.item_id')
            ->leftJoin('item_categories as cat', 'cat.id', '=', 'items.category_id')
            ->selectRaw("COALESCE(cat.name, 'Tanpa Kategori') as category, COALESCE(cat.color_code, '#6c757d') as color, SUM(pac.quantity_stored) as qty, COUNT(DISTINCT items.id) as skus")
            ->whereYear('pac.created_at', $year)
            ->groupBy('cat.id', 'cat.name', 'cat.color_code')
            ->orderByDesc('qty')
            ->get();

        // Available years
        $years = DB::table('stock_records')
            ->selectRaw('YEAR(created_at) as yr')
            ->groupByRaw('YEAR(created_at)')
            ->orderByDesc('yr')
            ->pluck('yr');

        // Chart data
        $months         = range(1, 12);
        $chartPutAway   = [];
        $chartQty       = [];
        foreach ($months as $m) {
            $chartPutAway[] = $monthlyPutAway[$m]->records ?? 0;
            $chartQty[]     = $monthlyPutAway[$m]->total_qty ?? 0;
        }

        $summary = [
            'total_ga'     => $gaStats->total ?? 0,
            'avg_fitness'  => round($gaStats->avg_fitness ?? 0, 4),
            'avg_exec_ms'  => round($gaStats->avg_exec_ms ?? 0),
            'approved_pct' => $gaStats->total > 0
                ? round(($gaStats->approved / $gaStats->total) * 100, 1) : 0,
        ];

        $traceBase = DB::table('put_away_confirmations as pac')
            ->join('inbound_details as idt', 'idt.id', '=', 'pac.inbound_order_item_id')
            ->join('inbound_transactions as it', 'it.id', '=', 'idt.inbound_order_id')
            ->join('items as i', 'i.id', '=', 'idt.item_id')
            ->leftJoin('units as u', 'u.id', '=', 'i.unit_id')
            ->join('cells as c', 'c.id', '=', 'pac.cell_id')
            ->leftJoin('users as usr', 'usr.id', '=', 'pac.user_id')
            ->when($hasFilter, fn($q) => $q->whereBetween('pac.confirmed_at', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59',
            ]))
            ->when($operatorId, fn($q) => $q->where('pac.user_id', $operatorId));

        $traceSummary = (clone $traceBase)
            ->selectRaw('
                COUNT(pac.id) as confirmation_count,
                COALESCE(SUM(pac.quantity_stored), 0) as total_qty,
                COUNT(DISTINCT it.id) as do_count,
                COUNT(DISTINCT i.id) as sku_count
            ')
            ->first();

        $confirmationLogs = (clone $traceBase)
            ->select([
                'pac.id',
                'pac.confirmed_at',
                'pac.quantity_stored',
                'pac.follow_recommendation',
                'pac.notes',
                'usr.name as operator_name',
                'it.do_number',
                'i.sku',
                'i.name as item_name',
                'u.code as unit_code',
                'c.code as cell_code',
                'c.blok',
                'c.grup',
                'c.kolom',
                'c.baris',
            ])
            ->orderByDesc('pac.confirmed_at')
            ->limit(200)
            ->get();

        $operatorStats = (clone $traceBase)
            ->selectRaw('COALESCE(usr.name, "User tidak aktif") as operator_name, COUNT(*) as confirmation_count, SUM(pac.quantity_stored) as total_qty')
            ->groupBy('pac.user_id', 'usr.name')
            ->orderByDesc('confirmation_count')
            ->get();

        $operators = DB::table('users')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return view('reports.putaway', compact(
            'summary', 'gaStats', 'byCategory',
            'chartPutAway', 'chartQty', 'year', 'years',
            'dateFrom', 'dateTo', 'operatorId', 'operators',
            'confirmationLogs', 'operatorStats', 'traceSummary'
        ));
    }

    // ─── 4. Laporan Mutasi Stok ──────────────────────────────────────────────
    public function movements(Request $request)
    {
        $year = $request->input('year', now()->year);

        // Trend per bulan
        $monthlyMov = DB::table('stock_movements')
            ->selectRaw('
                MONTH(created_at) as month,
                SUM(CASE WHEN movement_type = "inbound" THEN quantity ELSE 0 END) as qty_in,
                SUM(CASE WHEN movement_type = "outbound" THEN quantity ELSE 0 END) as qty_out,
                SUM(CASE WHEN movement_type = "transfer" THEN quantity ELSE 0 END) as qty_transfer,
                COUNT(*) as total_txn
            ')
            ->whereYear('created_at', $year)
            ->groupByRaw('MONTH(created_at)')
            ->orderByRaw('MONTH(created_at)')
            ->get()
            ->keyBy('month');

        // Top 10 item paling aktif
        $topActive = DB::table('stock_movements as sm')
            ->join('items', 'items.id', '=', 'sm.item_id')
            ->selectRaw('items.id as item_id, items.name, items.sku, COUNT(*) as txn_count, SUM(sm.quantity) as total_qty')
            ->whereYear('sm.created_at', $year)
            ->groupBy('items.id', 'items.name', 'items.sku')
            ->orderByDesc('txn_count')
            ->limit(10)
            ->get();

        // Distribusi tipe mutasi
        $typeDist = DB::table('stock_movements')
            ->selectRaw('movement_type, COUNT(*) as total, SUM(quantity) as total_qty')
            ->whereYear('created_at', $year)
            ->groupBy('movement_type')
            ->get();

        // Available years
        $years = DB::table('stock_movements')
            ->selectRaw('YEAR(created_at) as yr')
            ->groupByRaw('YEAR(created_at)')
            ->orderByDesc('yr')
            ->pluck('yr');

        if ($years->isEmpty()) $years = collect([now()->year]);

        // Chart arrays
        $months      = range(1, 12);
        $chartIn     = [];
        $chartOut    = [];
        $chartTrans  = [];
        foreach ($months as $m) {
            $chartIn[]    = (int)($monthlyMov[$m]->qty_in ?? 0);
            $chartOut[]   = (int)($monthlyMov[$m]->qty_out ?? 0);
            $chartTrans[] = (int)($monthlyMov[$m]->qty_transfer ?? 0);
        }

        $summary = [
            'total_txn'    => DB::table('stock_movements')->whereYear('created_at', $year)->count(),
            'total_in'     => DB::table('stock_movements')->whereYear('created_at', $year)->where('movement_type','inbound')->sum('quantity'),
            'total_out'    => DB::table('stock_movements')->whereYear('created_at', $year)->where('movement_type','outbound')->sum('quantity'),
            'total_trans'  => DB::table('stock_movements')->whereYear('created_at', $year)->where('movement_type','transfer')->count(),
        ];

        return view('reports.movements', compact(
            'summary', 'topActive', 'typeDist',
            'chartIn', 'chartOut', 'chartTrans', 'year', 'years'
        ));
    }

    // ─── 5. Efektivitas GA ──────────────────────────────────────────────────
    public function gaEffectiveness(Request $request)
    {
        $year = $request->input('year', now()->year);

        // Fitness trend per bulan
        $monthlyFitness = DB::table('ga_recommendations')
            ->selectRaw('
                MONTH(created_at) as month,
                COUNT(*) as total,
                AVG(fitness_score) as avg_fitness,
                MAX(fitness_score) as max_fitness,
                MIN(fitness_score) as min_fitness,
                AVG(execution_time_ms) as avg_exec_ms,
                AVG(generations_run) as avg_generations
            ')
            ->whereYear('created_at', $year)
            ->whereNotNull('fitness_score')
            ->groupByRaw('MONTH(created_at)')
            ->orderByRaw('MONTH(created_at)')
            ->get()
            ->keyBy('month');

        // Distribusi fitness score (bucket 0.1)
        $fitnessDistribution = DB::table('ga_recommendations')
            ->selectRaw('FLOOR(fitness_score * 10) / 10 as bucket, COUNT(*) as cnt')
            ->whereYear('created_at', $year)
            ->whereNotNull('fitness_score')
            ->groupByRaw('FLOOR(fitness_score * 10) / 10')
            ->orderBy('bucket')
            ->get();

        // Compliance rate: berapa persen rekomendasi GA diikuti vs di-override
        // (from put_away_confirmations: follow_recommendation field)
        $compliance = DB::table('put_away_confirmations as pac')
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN pac.follow_recommendation = 1 THEN 1 ELSE 0 END) as followed,
                SUM(CASE WHEN pac.follow_recommendation = 0 THEN 1 ELSE 0 END) as overridden
            ')
            ->whereYear('pac.created_at', $year)
            ->first();

        // ── Metrik Efektivitas Penempatan (Slot Efficiency) ──────────────────
        // a) Split location: item yang tersebar di lebih dari 1 cell
        $stockLocations = DB::table('stock_records')
            ->where('status', 'available')
            ->groupBy('item_id')
            ->select('item_id', DB::raw('COUNT(DISTINCT cell_id) as cell_count'))
            ->get();

        $splitLocationCount = $stockLocations->where('cell_count', '>', 1)->count();
        $avgLocationsPerSku = $stockLocations->count() > 0
            ? round($stockLocations->avg('cell_count'), 2) : 0;

        // b) Utilisasi kapasitas rak (seluruh gudang, snapshot saat ini)
        $capStats = DB::table('cells')
            ->where('is_active', true)
            ->selectRaw('SUM(capacity_used) as used, SUM(capacity_max) as total_cap')
            ->first();
        $rackUtilization = ($capStats->total_cap ?? 0) > 0
            ? round(($capStats->used / $capStats->total_cap) * 100, 1) : 0;

        // c) Estimasi waktu put-away: rata-rata durasi order dari created → completed
        $avgPutAwayMinutes = (int) round(
            DB::table('inbound_transactions')
                ->where('status', 'completed')
                ->whereYear('created_at', $year)
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as avg_min')
                ->value('avg_min') ?? 0
        );

        // ── Perbandingan Skenario Pengujian ───────────────────────────────────
        // Skenario 2: Simulasi Acak — distribusi probabilistik ke seluruh sel aktif
        $totalActiveCells = max(DB::table('cells')->where('is_active', true)->count(), 1);

        // Basis simulasi: distribusi record per item di stok aktif (T_i = jumlah record)
        $itemPutCounts = DB::table('stock_records')
            ->where('status', 'available')
            ->groupBy('item_id')
            ->select('item_id', DB::raw('COUNT(*) as put_count'))
            ->get();

        $C = $totalActiveCells;
        $randomSplitSum   = 0.0;
        $randomLocSum     = 0.0;
        foreach ($itemPutCounts as $row) {
            $T = max((int) $row->put_count, 1);
            // P(split | random) = 1 − (1/C)^(T−1): P(all T placements land in same cell)
            $randomSplitSum += $T > 1 ? (1 - pow(1 / $C, $T - 1)) : 0;
            // E[distinct cells] = C × (1 − (1 − 1/C)^T)  [occupancy / birthday-problem]
            $randomLocSum += $C * (1 - pow(1 - 1 / $C, $T));
        }
        $randomSplitCount     = (int) round($randomSplitSum);
        $randomAvgLocPerSku   = $itemPutCounts->count() > 0
            ? round($randomLocSum / $itemPutCounts->count(), 2) : 0;
        $randomPutAwayMinutes = $avgPutAwayMinutes > 0
            ? (int) round($avgPutAwayMinutes * 1.20) : 0;

        // Skenario 3: Rekomendasi GA — hanya dari confirmasi yang follow_recommendation = 1
        $gaFollowedCount = DB::table('put_away_confirmations')
            ->where('follow_recommendation', 1)
            ->whereYear('created_at', $year)
            ->count();

        $gaFollowedLocs = DB::table('put_away_confirmations as pac')
            ->join('inbound_details as id2', 'id2.id', '=', 'pac.inbound_order_item_id')
            ->where('pac.follow_recommendation', 1)
            ->groupBy('id2.item_id')
            ->select('id2.item_id', DB::raw('COUNT(DISTINCT pac.cell_id) as cell_count'))
            ->get();

        $gaScenarioSplit  = $gaFollowedLocs->where('cell_count', '>', 1)->count();
        $gaScenarioAvgLoc = $gaFollowedLocs->count() > 0
            ? round($gaFollowedLocs->avg('cell_count'), 2) : 0;
        $gaScenarioMinutes = (int) round(
            DB::table('inbound_transactions as it')
                ->join('ga_recommendations as gar', 'gar.inbound_order_id', '=', 'it.id')
                ->where('it.status', 'completed')
                ->whereIn('gar.status', ['accepted', 'pending_review'])
                ->whereYear('it.created_at', $year)
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, it.created_at, it.updated_at)) as avg_min')
                ->value('avg_min') ?? 0
        );

        $scenarioComparison = [
            [
                'label'         => 'Kondisi Aktual',
                'desc'          => 'Snapshot stok gudang saat ini',
                'badge'         => 'secondary',
                'split_count'   => $splitLocationCount,
                'avg_loc'       => $avgLocationsPerSku,
                'utilization'   => $rackUtilization,
                'putaway_min'   => $avgPutAwayMinutes,
                'is_simulated'  => false,
                'is_ga'         => false,
            ],
            [
                'label'         => 'Penempatan Acak',
                'desc'          => 'Simulasi tanpa optimasi (probabilistik)',
                'badge'         => 'danger',
                'split_count'   => $randomSplitCount,
                'avg_loc'       => $randomAvgLocPerSku,
                'utilization'   => $rackUtilization,
                'putaway_min'   => $randomPutAwayMinutes,
                'is_simulated'  => true,
                'is_ga'         => false,
            ],
            [
                'label'         => 'Rekomendasi GA',
                'desc'          => 'Konfirmasi yang mengikuti saran GA',
                'badge'         => 'success',
                'split_count'   => $gaScenarioSplit,
                'avg_loc'       => $gaScenarioAvgLoc,
                'utilization'   => $rackUtilization,
                'putaway_min'   => $gaScenarioMinutes,
                'is_simulated'  => false,
                'is_ga'         => true,
            ],
        ];

        // Available years
        $years = DB::table('ga_recommendations')
            ->selectRaw('YEAR(created_at) as yr')
            ->groupByRaw('YEAR(created_at)')
            ->orderByDesc('yr')
            ->pluck('yr');

        if ($years->isEmpty()) $years = collect([now()->year]);

        // All GA records for this year (list)
        $gaRecords = GaRecommendation::with(['inboundOrder', 'generatedBy'])
            ->whereYear('created_at', $year)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        // Summary
        $overallStats = DB::table('ga_recommendations')
            ->whereYear('created_at', $year)
            ->selectRaw('
                COUNT(*) as total,
                AVG(fitness_score) as avg_fitness,
                MAX(fitness_score) as best_fitness,
                AVG(execution_time_ms) as avg_exec_ms,
                AVG(generations_run) as avg_gen
            ')
            ->first();

        // Chart arrays
        $months         = range(1, 12);
        $chartAvgFit    = [];
        $chartMaxFit    = [];
        $chartExecMs    = [];
        foreach ($months as $m) {
            $row = $monthlyFitness->get($m);
            $chartAvgFit[] = $row?->avg_fitness ? round($row->avg_fitness, 4) : null;
            $chartMaxFit[] = $row?->max_fitness ? round($row->max_fitness, 4) : null;
            $chartExecMs[] = $row?->avg_exec_ms ? round($row->avg_exec_ms) : null;
        }

        $compliancePct = ($compliance->total ?? 0) > 0
            ? round(($compliance->followed / $compliance->total) * 100, 1) : 0;

        $summary = [
            'total_ga'              => $overallStats->total ?? 0,
            'avg_fitness'           => round($overallStats->avg_fitness ?? 0, 4),
            'best_fitness'          => round($overallStats->best_fitness ?? 0, 4),
            'avg_exec_ms'           => round($overallStats->avg_exec_ms ?? 0),
            'compliance_pct'        => $compliancePct,
            // Slot efficiency metrics
            'split_location_count'  => $splitLocationCount,
            'avg_locations_per_sku' => $avgLocationsPerSku,
            'rack_utilization'      => $rackUtilization,
            'avg_putaway_minutes'   => $avgPutAwayMinutes,
        ];

        return view('reports.ga-effectiveness', compact(
            'summary', 'compliance', 'compliancePct', 'fitnessDistribution', 'gaRecords',
            'chartAvgFit', 'chartMaxFit', 'chartExecMs', 'year', 'years',
            'scenarioComparison', 'totalActiveCells', 'itemPutCounts', 'gaFollowedCount'
        ));
    }

    // ─── Helper ─────────────────────────────────────────────────────────────
    private function countBelowMin(): int
    {
        $stockPerItem = DB::table('stock_records')
            ->where('status', 'available')
            ->groupBy('item_id')
            ->select('item_id', DB::raw('SUM(quantity) as total_qty'));

        return DB::table('items')
            ->joinSub($stockPerItem, 'stk', fn($j) => $j->on('stk.item_id','=','items.id'))
            ->where('items.is_active', true)
            ->whereColumn('stk.total_qty', '<', 'items.min_stock')
            ->count();
    }

    // ─── Export (placeholder) ────────────────────────────────────────────────
    public function export(Request $request, string $type)
    {
        abort(501, 'Export feature coming soon.');
    }
}
