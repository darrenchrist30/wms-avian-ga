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

        // Utilization gudang (cell terpakai vs tersedia)
        $warehouseUtil = DB::table('warehouses as w')
            ->leftJoin('zones as z', 'z.warehouse_id', '=', 'w.id')
            ->leftJoin('racks as r', 'r.zone_id', '=', 'z.id')
            ->leftJoin('cells as c', 'c.rack_id', '=', 'r.id')
            ->leftJoin(DB::raw('(SELECT cell_id, SUM(quantity) as qty FROM stock_records WHERE status="available" GROUP BY cell_id HAVING qty > 0) as stk'), 'stk.cell_id', '=', 'c.id')
            ->where('w.is_active', true)
            ->groupBy('w.id', 'w.name')
            ->select([
                'w.name as warehouse',
                DB::raw('COUNT(c.id) as total_cells'),
                DB::raw('COUNT(stk.cell_id) as used_cells'),
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
            'near_expiry'  => Stock::where('status', 'available')
                                ->whereNotNull('expiry_date')
                                ->whereDate('expiry_date', '<=', now()->addDays(30))
                                ->distinct('item_id')->count('item_id'),
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

        // Inbound per supplier (top 10)
        $bySupplier = DB::table('inbound_transactions as it')
            ->leftJoin('suppliers as s', 's.id', '=', 'it.supplier_id')
            ->selectRaw("COALESCE(s.name, 'Manual') as supplier, COUNT(*) as order_count")
            ->whereYear('it.created_at', $year)
            ->groupBy('s.id', 's.name')
            ->orderByDesc('order_count')
            ->limit(10)
            ->get();

        // Distribusi status
        $statusDist = DB::table('inbound_transactions')
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
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
            'total_suppliers' => DB::table('inbound_transactions')->whereYear('created_at', $year)
                                    ->distinct('supplier_id')->count('supplier_id'),
        ];

        // Build 12-month arrays for chart
        $months       = range(1, 12);
        $chartOrders  = [];
        $chartQty     = [];
        foreach ($months as $m) {
            $chartOrders[] = $monthlyQty[$m]->order_count ?? 0;
            $chartQty[]    = $monthlyQty[$m]->total_qty ?? 0;
        }

        return view('reports.inbound', compact(
            'summary', 'bySupplier', 'statusDist',
            'chartOrders', 'chartQty', 'year', 'years'
        ));
    }

    // ─── 3. Laporan Put-Away ─────────────────────────────────────────────────
    public function putaway(Request $request)
    {
        $year = $request->input('year', now()->year);

        // Put-away per bulan (dari stock_records yang masuk)
        $monthlyPutAway = DB::table('stock_records')
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as records, SUM(quantity) as total_qty')
            ->whereYear('created_at', $year)
            ->where('status', 'available')
            ->groupByRaw('MONTH(created_at)')
            ->orderByRaw('MONTH(created_at)')
            ->get()
            ->keyBy('month');

        // GA recommendation stats
        $gaStats = DB::table('ga_recommendations')
            ->selectRaw('
                COUNT(*) as total,
                AVG(fitness_score) as avg_fitness,
                AVG(execution_time_ms) as avg_exec_ms,
                AVG(generations_run) as avg_generations,
                SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected
            ')
            ->whereYear('created_at', $year)
            ->first();

        // Put-away per kategori item
        $byCategory = DB::table('stock_records as sr')
            ->join('items', 'items.id', '=', 'sr.item_id')
            ->leftJoin('item_categories as cat', 'cat.id', '=', 'items.category_id')
            ->selectRaw("COALESCE(cat.name, 'Tanpa Kategori') as category, COALESCE(cat.color_code, '#6c757d') as color, SUM(sr.quantity) as qty, COUNT(DISTINCT sr.item_id) as skus")
            ->whereYear('sr.created_at', $year)
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

        return view('reports.putaway', compact(
            'summary', 'gaStats', 'byCategory',
            'chartPutAway', 'chartQty', 'year', 'years'
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
            ->selectRaw('items.name, items.sku, COUNT(*) as txn_count, SUM(sm.quantity) as total_qty')
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
            $chartAvgFit[] = $monthlyFitness[$m]->avg_fitness
                ? round($monthlyFitness[$m]->avg_fitness, 4) : null;
            $chartMaxFit[] = $monthlyFitness[$m]->max_fitness
                ? round($monthlyFitness[$m]->max_fitness, 4) : null;
            $chartExecMs[] = $monthlyFitness[$m]->avg_exec_ms
                ? round($monthlyFitness[$m]->avg_exec_ms) : null;
        }

        $compliancePct = ($compliance->total ?? 0) > 0
            ? round(($compliance->followed / $compliance->total) * 100, 1) : 0;

        $summary = [
            'total_ga'       => $overallStats->total ?? 0,
            'avg_fitness'    => round($overallStats->avg_fitness ?? 0, 4),
            'best_fitness'   => round($overallStats->best_fitness ?? 0, 4),
            'avg_exec_ms'    => round($overallStats->avg_exec_ms ?? 0),
            'compliance_pct' => $compliancePct,
        ];

        return view('reports.ga-effectiveness', compact(
            'summary', 'compliance', 'compliancePct', 'fitnessDistribution', 'gaRecords',
            'chartAvgFit', 'chartMaxFit', 'chartExecMs', 'year', 'years'
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
