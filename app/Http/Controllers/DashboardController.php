<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\GaRecommendation;
use App\Models\InboundOrder;
use App\Models\Item;
use App\Models\Cell;
use App\Models\PutAwayConfirmation;
use App\Models\Rack;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Zone;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        $deadstockDays = max(1, (int) $request->input('deadstock_days', 90));

        // ── KPI Utama ───────────────────────────────────────────────────────────
        $totalItems     = Item::where('is_active', true)->count();
        $totalCells     = Cell::where('is_active', true)->count();
        $usedCells      = Cell::where('is_active', true)->whereIn('status', ['partial', 'full'])->count();
        $utilizationPct = $totalCells > 0 ? round($usedCells / $totalCells * 100, 1) : 0;
        $totalStockQty  = Stock::where('status', 'available')->sum('quantity');

        $inboundToday  = InboundOrder::whereDate('received_at', today())->count();
        $outboundToday = StockMovement::ofType('outbound')->today()->count();

        $lowStockItems = Item::where('is_active', true)
            ->whereRaw('(SELECT COALESCE(SUM(s.quantity),0) FROM stock_records s WHERE s.item_id = items.id AND s.status = "available") < items.min_stock')
            ->count();
        $nearExpiryItems = Stock::nearExpiry(30)->count();
        $activeOrders    = InboundOrder::whereIn('status', ['inbound', 'put_away'])->count();
        $deadstockCount  = Stock::deadstock($deadstockDays)->distinct('item_id')->count('item_id');

        // ── Zona Gudang ─────────────────────────────────────────────────────────
        $zones = Zone::with('racks.cells')->get()->map(function ($zone) {
            $cells = $zone->cells;
            $max   = $cells->sum('capacity_max');
            $used  = $cells->sum('capacity_used');
            $pct   = $max > 0 ? round($used / $max * 100, 1) : 0;
            return [
                'name'    => $zone->name,
                'code'    => $zone->code,
                'max'     => $max,
                'used'    => $used,
                'free'    => $max - $used,
                'percent' => $pct,
            ];
        });

        $fullZones = $zones->filter(fn($z) => $z['percent'] >= 85)->values();

        // ── Order Status Breakdown (real) ────────────────────────────────────────
        $orderStatusCounts = InboundOrder::selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        // ── Grafik Arus Barang 7 Hari (real) ────────────────────────────────────
        $chartDays = collect(range(6, 0))->map(fn($d) => now()->subDays($d));

        $inboundByDay = StockMovement::ofType('inbound')
            ->selectRaw('DATE(created_at) as date, COALESCE(SUM(quantity), 0) as qty')
            ->whereBetween('created_at', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
            ->groupBy('date')
            ->pluck('qty', 'date');

        $outboundByDay = StockMovement::ofType('outbound')
            ->selectRaw('DATE(created_at) as date, COALESCE(SUM(quantity), 0) as qty')
            ->whereBetween('created_at', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
            ->groupBy('date')
            ->pluck('qty', 'date');

        $chartLabels   = $chartDays->map(fn($d) => $d->isoFormat('ddd D/M'))->toArray();
        $chartInbound  = $chartDays->map(fn($d) => (int) ($inboundByDay[$d->format('Y-m-d')] ?? 0))->toArray();
        $chartOutbound = $chartDays->map(fn($d) => (int) ($outboundByDay[$d->format('Y-m-d')] ?? 0))->toArray();

        // ── Stok per Kategori (real) ─────────────────────────────────────────────
        $stockByCategory = DB::table('stock_records')
            ->join('items', 'stock_records.item_id', '=', 'items.id')
            ->join('item_categories', 'items.category_id', '=', 'item_categories.id')
            ->where('stock_records.status', 'available')
            ->selectRaw('item_categories.name, item_categories.color_code, SUM(stock_records.quantity) as total_qty, COUNT(DISTINCT stock_records.item_id) as item_count')
            ->groupBy('item_categories.id', 'item_categories.name', 'item_categories.color_code')
            ->orderByDesc('total_qty')
            ->get();

        // ── Top 5 SKU Paling Aktif (real) ────────────────────────────────────────
        $topMovedItems = StockMovement::with('item.unit')
            ->selectRaw('item_id, SUM(quantity) as total_qty, COUNT(*) as movement_count')
            ->whereNotNull('item_id')
            ->groupBy('item_id')
            ->orderByDesc('total_qty')
            ->take(5)
            ->get();

        // ── Aktivitas Put-Away Hari Ini per User (real) ───────────────────────────
        $putAwayToday = PutAwayConfirmation::with('user')
            ->whereDate('confirmed_at', today())
            ->selectRaw('user_id, COUNT(*) as items_count, SUM(quantity_stored) as total_qty,
                         SUM(CASE WHEN follow_recommendation = 1 THEN 1 ELSE 0 END) as follow_ga_count')
            ->groupBy('user_id')
            ->orderByDesc('items_count')
            ->take(8)
            ->get();

        $putAwayTodayTotal = PutAwayConfirmation::whereDate('confirmed_at', today())->count();

        // ── Statistik GA (real) ──────────────────────────────────────────────────
        $gaTotal      = GaRecommendation::count();
        $gaAccepted   = GaRecommendation::where('status', 'accepted')->count();
        $gaRejected   = GaRecommendation::where('status', 'rejected')->count();
        $gaAcceptRate = $gaTotal > 0 ? round($gaAccepted / $gaTotal * 100, 1) : 0;
        $gaAvgFitness = round(GaRecommendation::where('status', 'accepted')->avg('fitness_score') ?? 0, 1);
        $gaAvgExecMs  = round(GaRecommendation::where('status', 'accepted')->avg('execution_time_ms') ?? 0);

        // ── Follow GA Rate (put-away sesuai rekomendasi) ─────────────────────────
        $totalConfirms  = PutAwayConfirmation::count();
        $followGaCount  = PutAwayConfirmation::where('follow_recommendation', true)->count();
        $followGaRate   = $totalConfirms > 0 ? round($followGaCount / $totalConfirms * 100, 1) : 0;

        // ── Completion Rate Bulan Ini ────────────────────────────────────────────
        $totalThisMonth     = InboundOrder::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)->count();
        $completedThisMonth = InboundOrder::where('status', 'completed')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)->count();
        $completionRate = $totalThisMonth > 0 ? round($completedThisMonth / $totalThisMonth * 100, 1) : 0;

        // ── Alert & Jadwal ──────────────────────────────────────────────────────
        $lowStockAlerts = Item::with('category')
            ->where('is_active', true)
            ->where('min_stock', '>', 0)
            ->whereRaw('(SELECT COALESCE(SUM(s.quantity),0) FROM stock_records s WHERE s.item_id = items.id AND s.status = "available") < items.min_stock')
            ->orderByRaw('(SELECT COALESCE(SUM(s.quantity),0) FROM stock_records s WHERE s.item_id = items.id AND s.status = "available") ASC')
            ->take(5)
            ->get()
            ->map(fn($item) => [
                'sku'     => $item->sku,
                'name'    => $item->name,
                'current' => $item->total_stock,
                'min'     => $item->min_stock,
            ]);

        $deadstockStocks = Stock::with(['item.category', 'cell.rack.zone'])
            ->deadstock($deadstockDays)
            ->orderByRaw('COALESCE(last_moved_at, inbound_date) ASC')
            ->take(8)
            ->get();

        $recentMovements = StockMovement::with(['item', 'fromCell.rack.zone', 'toCell.rack.zone', 'performedBy'])
            ->latest()
            ->take(10)
            ->get();

        $expiryStocks = Stock::with(['item', 'cell.rack.zone'])
            ->nearExpiry(90)
            ->orderBy('expiry_date')
            ->take(10)
            ->get();

        $scheduledInbound = InboundOrder::with(['supplier', 'items'])
            ->whereIn('status', ['inbound', 'put_away'])
            ->orderBy('do_date')
            ->take(5)
            ->get();

        // ── Actionable cards ─────────────────────────────────────────────────────
        // DO yang do_date-nya hari ini dan statusnya masih inbound (baru datang, belum diproses GA)
        $inboundHariIni = InboundOrder::whereDate('do_date', today())
            ->where('status', 'inbound')
            ->count();

        $pendingQtyConfirm = 0; // tidak ada lagi langkah konfirmasi qty

        $pendingGaRun = InboundOrder::where('status', 'inbound')->count();

        $pendingGaAccept = 0; // tidak ada lagi langkah review supervisor

        $pendingPutAway = InboundOrder::where('status', 'put_away')
            ->whereHas('gaRecommendations', fn($q) => $q->where('status', 'accepted'))
            ->count();

        // ── Process Control: funnel, bottleneck, and aging ─────────────────
        $completedOrders = InboundOrder::where('status', 'completed')->count();

        $processFunnel = [
            ['label' => 'Inbound',   'count' => (int) ($orderStatusCounts['inbound'] ?? 0), 'color' => '#f59e0b'],
            ['label' => 'Put-Away',  'count' => $pendingPutAway,                             'color' => '#14b8a6'],
            ['label' => 'Completed', 'count' => $completedOrders,                            'color' => '#0d8564'],
        ];

        $bottleneckSummary = [
            [
                'label'       => 'Belum GA',
                'count'       => $pendingGaRun,
                'oldest_days' => (int) optional(
                    InboundOrder::where('status', 'inbound')
                        ->oldest('created_at')
                        ->first()
                )?->created_at?->diffInDays(now()),
                'color'       => '#f59e0b',
                'url'         => route('inbound.orders.index', ['status' => 'inbound']),
                'url_label'   => 'Jalankan GA',
            ],
            [
                'label'       => 'Belum Put-Away',
                'count'       => $pendingPutAway,
                'oldest_days' => (int) optional(
                    InboundOrder::where('status', 'put_away')
                        ->whereHas('gaRecommendations', fn($q) => $q->where('status', 'accepted'))
                        ->oldest('updated_at')
                        ->first()
                )?->updated_at?->diffInDays(now()),
                'color'       => '#0d8564',
                'url'         => route('putaway.index'),
                'url_label'   => 'Mulai Put-Away',
            ],
        ];

        $oldestOpenOrders = InboundOrder::with(['warehouse', 'supplier'])
            ->whereIn('status', ['inbound', 'put_away'])
            ->orderBy('created_at')
            ->take(4)
            ->get()
            ->map(fn($order) => [
                'id'        => $order->id,
                'do_number' => $order->do_number,
                'status'    => $order->status,
                'warehouse' => $order->warehouse?->name ?? '-',
                'supplier'  => $order->supplier?->name ?? '-',
                'age_days'  => $order->created_at?->diffInDays(now()) ?? 0,
            ]);

        // ── GA analytics for thesis and operational monitoring ─────────────
        $gaBestFitness  = round(GaRecommendation::max('fitness_score') ?? 0, 1);
        $gaWorstFitness = round(GaRecommendation::min('fitness_score') ?? 0, 1);
        $gaAvgGen       = round(GaRecommendation::avg('generations_run') ?? 0);
        $gaOverrideRate = $totalConfirms > 0 ? round(($totalConfirms - $followGaCount) / $totalConfirms * 100, 1) : 0;

        $gaFitnessDistribution = [
            '0-50'   => GaRecommendation::whereBetween('fitness_score', [0, 50])->count(),
            '51-70'  => GaRecommendation::where('fitness_score', '>', 50)->where('fitness_score', '<=', 70)->count(),
            '71-85'  => GaRecommendation::where('fitness_score', '>', 70)->where('fitness_score', '<=', 85)->count(),
            '86-100' => GaRecommendation::where('fitness_score', '>', 85)->where('fitness_score', '<=', 100)->count(),
        ];

        $gaTrendDays = collect(range(6, 0))->map(fn($d) => now()->subDays($d));
        $gaRunsByDay = GaRecommendation::selectRaw('DATE(generated_at) as date, COUNT(*) as total, AVG(fitness_score) as avg_fitness')
            ->whereBetween('generated_at', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $gaTrendLabels  = $gaTrendDays->map(fn($d) => $d->isoFormat('ddd D/M'))->toArray();
        $gaTrendRuns    = $gaTrendDays->map(fn($d) => (int) ($gaRunsByDay[$d->format('Y-m-d')]->total ?? 0))->toArray();
        $gaTrendFitness = $gaTrendDays->map(fn($d) => round((float) ($gaRunsByDay[$d->format('Y-m-d')]->avg_fitness ?? 0), 1))->toArray();

        // ── Warehouse capacity drill-down ──────────────────────────────────
        $topRacksUtilization = Rack::with('zone')
            ->where('is_active', true)
            ->withSum('cells as capacity_used_sum', 'capacity_used')
            ->withSum('cells as capacity_max_sum', 'capacity_max')
            ->get()
            ->map(function ($rack) {
                $max  = (int) ($rack->capacity_max_sum ?? 0);
                $used = (int) ($rack->capacity_used_sum ?? 0);
                return [
                    'code'    => $rack->code,
                    'name'    => $rack->name,
                    'zone'    => $rack->zone?->name ?? '-',
                    'used'    => $used,
                    'max'     => $max,
                    'percent' => $max > 0 ? round($used / $max * 100, 1) : 0,
                ];
            })
            ->sortByDesc('percent')
            ->take(8)
            ->values();

        $criticalCells = Cell::with('rack.zone')
            ->where('is_active', true)
            ->where('capacity_max', '>', 0)
            ->get()
            ->map(fn($cell) => [
                'code'      => $cell->code,
                'rack'      => $cell->rack?->code ?? '-',
                'zone'      => $cell->rack?->zone?->name ?? '-',
                'used'      => $cell->capacity_used,
                'max'       => $cell->capacity_max,
                'remaining' => max(0, $cell->capacity_max - $cell->capacity_used),
                'percent'   => round($cell->capacity_used / $cell->capacity_max * 100, 1),
                'status'    => $cell->status,
            ])
            ->sortByDesc('percent')
            ->take(8)
            ->values();

        // ── Visual analytics: inventory risk and operator productivity ─────
        $lowStockRows = Item::where('is_active', true)
            ->where('min_stock', '>', 0)
            ->select('items.*')
            ->selectSub(function ($q) {
                $q->from('stock_records')
                    ->selectRaw('COALESCE(SUM(quantity), 0)')
                    ->whereColumn('stock_records.item_id', 'items.id')
                    ->where('stock_records.status', 'available');
            }, 'available_qty')
            ->get();

        $lowStockCritical = $lowStockRows
            ->filter(fn($item) => (int) $item->available_qty <= max(1, floor($item->min_stock * 0.5)))
            ->count();
        $lowStockWarning = $lowStockRows
            ->filter(fn($item) => (int) $item->available_qty > max(1, floor($item->min_stock * 0.5))
                && (int) $item->available_qty < (int) $item->min_stock)
            ->count();

        $inventoryRiskChart = [
            'Low Stock Kritis' => $lowStockCritical,
            'Low Stock Warning' => $lowStockWarning,
            'Near Expiry' => $nearExpiryItems,
            'Deadstock' => $deadstockCount,
        ];

        $expiryBucketChart = [
            '<= 7 hari' => Stock::where('status', 'available')
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<=', now()->addDays(7))
                ->count(),
            '8-30 hari' => Stock::where('status', 'available')
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>', now()->addDays(7))
                ->whereDate('expiry_date', '<=', now()->addDays(30))
                ->count(),
            '31-90 hari' => Stock::where('status', 'available')
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>', now()->addDays(30))
                ->whereDate('expiry_date', '<=', now()->addDays(90))
                ->count(),
        ];

        $deadstockBucketChart = [
            '90-179 hari' => Stock::deadstock(90)->get()
                ->filter(fn($s) => ($s->days_since_last_movement ?? 0) < 180)
                ->count(),
            '180-364 hari' => Stock::deadstock(180)->get()
                ->filter(fn($s) => ($s->days_since_last_movement ?? 0) < 365)
                ->count(),
            '>= 365 hari' => Stock::deadstock(365)->count(),
        ];

        $operatorProductivity = $putAwayToday
            ->map(fn($pa) => [
                'name' => $pa->user?->name ?? 'N/A',
                'items' => (int) $pa->items_count,
                'qty' => (int) $pa->total_qty,
                'follow_rate' => $pa->items_count > 0 ? round($pa->follow_ga_count / $pa->items_count * 100, 1) : 0,
            ])
            ->values();

        return view('dashboard', compact(
            // KPI
            'totalItems', 'totalCells', 'usedCells', 'utilizationPct', 'totalStockQty',
            'inboundToday', 'outboundToday', 'lowStockItems',
            'nearExpiryItems', 'activeOrders', 'deadstockCount', 'deadstockDays',
            // Zona
            'zones', 'fullZones',
            // Order status
            'orderStatusCounts',
            // Chart 7 hari
            'chartLabels', 'chartInbound', 'chartOutbound',
            // Stok per kategori
            'stockByCategory',
            // Top SKU
            'topMovedItems',
            // Put-away hari ini
            'putAwayToday', 'putAwayTodayTotal',
            // GA
            'gaTotal', 'gaAccepted', 'gaRejected', 'gaAcceptRate', 'gaAvgFitness', 'gaAvgExecMs',
            // Rates
            'followGaRate', 'totalConfirms', 'followGaCount',
            'completionRate', 'totalThisMonth', 'completedThisMonth',
            // Alert & jadwal
            'lowStockAlerts', 'deadstockStocks', 'recentMovements',
            'expiryStocks', 'scheduledInbound',
            // Actionable
            'inboundHariIni', 'pendingQtyConfirm', 'pendingGaRun', 'pendingGaAccept', 'pendingPutAway',
            // Process control
            'processFunnel', 'bottleneckSummary', 'oldestOpenOrders',
            // GA analytics
            'gaBestFitness', 'gaWorstFitness', 'gaAvgGen', 'gaOverrideRate',
            'gaFitnessDistribution', 'gaTrendLabels', 'gaTrendRuns', 'gaTrendFitness',
            // Capacity drill-down
            'topRacksUtilization', 'criticalCells',
            // Visual analytics
            'inventoryRiskChart', 'expiryBucketChart', 'deadstockBucketChart', 'operatorProductivity'
        ));
    }

    public function sendWaAlert(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        \Illuminate\Support\Facades\Artisan::call('wms:send-wa-alert', ['--force' => true]);
        $output = trim(\Illuminate\Support\Facades\Artisan::output());

        return back()->with(
            str_contains(strtolower($output), 'berhasil') || str_contains($output, '1/') ? 'wa_success' : 'wa_info',
            $output ?: 'WA Alert diproses. Cek log jika FONNTE_TOKEN belum diisi.'
        );
    }

    public function trendData(\Illuminate\Http\Request $request)
    {
        $from = $request->date_from ? Carbon::parse($request->date_from)->startOfDay() : now()->subDays(6)->startOfDay();
        $to   = $request->date_to   ? Carbon::parse($request->date_to)->endOfDay()     : now()->endOfDay();

        if ($from->gt($to)) [$from, $to] = [$to, $from];
        if ($from->diffInDays($to) > 90) $from = $to->copy()->subDays(90);

        $days = collect();
        $cur  = $from->copy()->startOfDay();
        while ($cur->lte($to)) {
            $days->push($cur->copy());
            $cur->addDay();
        }

        $inboundByDay = StockMovement::ofType('inbound')
            ->selectRaw('DATE(created_at) as date, COALESCE(SUM(quantity), 0) as qty')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('date')
            ->pluck('qty', 'date');

        $outboundByDay = StockMovement::ofType('outbound')
            ->selectRaw('DATE(created_at) as date, COALESCE(SUM(quantity), 0) as qty')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('date')
            ->pluck('qty', 'date');

        $labels   = $days->map(fn($d) => $d->isoFormat('ddd D/M'))->toArray();
        $inbound  = $days->map(fn($d) => (int) ($inboundByDay[$d->format('Y-m-d')] ?? 0))->toArray();
        $outbound = $days->map(fn($d) => (int) ($outboundByDay[$d->format('Y-m-d')] ?? 0))->toArray();

        return response()->json([
            'labels'         => $labels,
            'inbound'        => $inbound,
            'outbound'       => $outbound,
            'total_inbound'  => array_sum($inbound),
            'total_outbound' => array_sum($outbound),
        ]);
    }
}
