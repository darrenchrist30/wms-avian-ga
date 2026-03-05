<?php

namespace App\Http\Controllers;

use App\Models\InboundOrder;
use App\Models\Item;
use App\Models\Cell;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Zone;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $totalItems     = Item::where('is_active', true)->count();
        $totalCells     = Cell::where('is_active', true)->count();
        $usedCells      = Cell::where('is_active', true)->whereIn('status', ['partial', 'full'])->count();
        $utilizationPct = $totalCells > 0 ? round($usedCells / $totalCells * 100, 1) : 0;

        $inboundToday    = InboundOrder::whereDate('received_at', today())->count();
        $outboundToday   = StockMovement::ofType('outbound')->today()->count();
        $lowStockItems   = Item::where('is_active', true)
            ->whereRaw('(SELECT COALESCE(SUM(s.quantity),0) FROM stocks s WHERE s.item_id = items.id AND s.status = "available") < items.min_stock')
            ->count();
        $nearExpiryItems = Stock::nearExpiry(30)->count();
        $activeOrders    = InboundOrder::whereIn('status', ['processing', 'recommended', 'put_away'])->count();
        $deadstockCount  = Stock::deadstock(90)->distinct('item_id')->count('item_id');

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

        $lowStockAlerts = Item::with('category')
            ->where('is_active', true)
            ->where('min_stock', '>', 0)
            ->whereRaw('(SELECT COALESCE(SUM(s.quantity),0) FROM stocks s WHERE s.item_id = items.id AND s.status = "available") < items.min_stock')
            ->orderByRaw('(SELECT COALESCE(SUM(s.quantity),0) FROM stocks s WHERE s.item_id = items.id AND s.status = "available") ASC')
            ->take(5)
            ->get()
            ->map(fn($item) => [
                'sku'     => $item->sku,
                'name'    => $item->name,
                'current' => $item->total_stock,
                'min'     => $item->min_stock,
            ]);

        $deadstockStocks = Stock::with(['item.category', 'cell.rack.zone'])
            ->deadstock(90)
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
            ->whereIn('status', ['draft', 'processing', 'recommended', 'put_away'])
            ->orderBy('do_date')
            ->take(5)
            ->get();

        return view('dashboard', compact(
            'totalItems', 'totalCells', 'usedCells', 'utilizationPct',
            'inboundToday', 'outboundToday', 'lowStockItems',
            'nearExpiryItems', 'activeOrders', 'deadstockCount',
            'zones', 'fullZones', 'lowStockAlerts', 'deadstockStocks',
            'recentMovements', 'expiryStocks', 'scheduledInbound'
        ));
    }
}