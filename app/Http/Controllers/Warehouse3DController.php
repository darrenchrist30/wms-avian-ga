<?php

namespace App\Http\Controllers;

use App\Models\Cell;
use App\Models\Rack;
use App\Models\Warehouse;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Warehouse3DController extends Controller
{
    // ─── View utama 3D / grid visualisasi ───────────────────────────────────
    public function index(Request $request)
    {
        $warehouseId = $request->input('warehouse_id');

        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();

        // Default: gudang pertama
        $selectedWarehouse = $warehouseId
            ? $warehouses->find($warehouseId)
            : $warehouses->first();

        // Summary utilisasi
        $summary = null;
        if ($selectedWarehouse) {
            $summary = [
                'total_zones' => Zone::where('warehouse_id', $selectedWarehouse->id)->count(),
                'total_racks' => Rack::whereHas('zone', fn($q) => $q->where('warehouse_id', $selectedWarehouse->id))->count(),
                'total_cells' => Cell::whereHas('rack.zone', fn($q) => $q->where('warehouse_id', $selectedWarehouse->id))->count(),
                'used_cells'  => Cell::whereHas('rack.zone', fn($q) => $q->where('warehouse_id', $selectedWarehouse->id))
                                    ->where('capacity_used', '>', 0)->count(),
                'full_cells'  => Cell::whereHas('rack.zone', fn($q) => $q->where('warehouse_id', $selectedWarehouse->id))
                                    ->where('status', 'full')->count(),
                'blocked_cells' => Cell::whereHas('rack.zone', fn($q) => $q->where('warehouse_id', $selectedWarehouse->id))
                                    ->where('status', 'blocked')->count(),
            ];
            $summary['utilization'] = $summary['total_cells'] > 0
                ? round(($summary['used_cells'] / $summary['total_cells']) * 100, 1) : 0;
        }

        return view('warehouse3d.index', compact('warehouses', 'selectedWarehouse', 'summary'));
    }

    // ─── JSON data grid visualisasi (per warehouse) ──────────────────────────
    public function data(Request $request)
    {
        $warehouseId = $request->input('warehouse_id');

        $zones = Zone::with([
            'racks' => function ($q) {
                $q->orderBy('code');
            },
            'racks.cells' => function ($q) {
                $q->orderBy('level')->orderBy('column');
            },
            'racks.cells.dominantCategory',
            'racks.cells.stocks' => function ($q) {
                $q->where('status', 'available')->select('cell_id', DB::raw('SUM(quantity) as qty'))
                  ->groupBy('cell_id');
            },
        ])
        ->where('warehouse_id', $warehouseId)
        ->orderBy('name')
        ->get();

        $data = $zones->map(function ($zone) {
            return [
                'zone_id'   => $zone->id,
                'zone_name' => $zone->name,
                'zone_code' => $zone->code,
                'zone_type' => $zone->zone_type ?? 'storage',
                'pos_x'     => (float) ($zone->pos_x ?? 0),
                'pos_z'     => (float) ($zone->pos_z ?? 0),
                'racks'     => $zone->racks->map(function ($rack) {
                    return [
                        'rack_id'       => $rack->id,
                        'rack_code'     => $rack->code,
                        'pos_x'         => (float) ($rack->pos_x ?? 0),
                        'pos_z'         => (float) ($rack->pos_z ?? 0),
                        'rotation_y'    => (float) ($rack->rotation_y ?? 0),
                        'total_levels'  => (int)   ($rack->total_levels ?? 7),
                        'total_columns' => (int)   ($rack->total_columns ?? 1),
                        'cells'     => $rack->cells->map(function ($cell) {
                            $utilPct = $cell->capacity_max > 0
                                ? round($cell->capacity_used / $cell->capacity_max * 100)
                                : 0;
                            return [
                                'cell_id'      => $cell->id,
                                'code'         => $cell->code,
                                'level'        => $cell->level,
                                'column'       => $cell->column,
                                'status'       => $cell->status,
                                'capacity_max' => $cell->capacity_max,
                                'capacity_used'=> $cell->capacity_used,
                                'utilization'  => $utilPct,
                                'category'     => $cell->dominantCategory
                                    ? ['name' => $cell->dominantCategory->name, 'color' => $cell->dominantCategory->color_code]
                                    : null,
                            ];
                        }),
                    ];
                }),
            ];
        });

        return response()->json($data);
    }

    // ─── Detail satu cell (AJAX popup) ──────────────────────────────────────
    public function cellDetail(Request $request, Cell $cell)
    {
        $cell->load([
            'rack.zone.warehouse',
            'dominantCategory',
            'stocks' => function ($q) {
                $q->where('status', 'available')
                  ->with('item.unit')
                  ->orderBy('inbound_date', 'asc');
            },
        ]);

        $stocks = $cell->stocks->map(function ($s) {
            return [
                'item_name'    => $s->item?->name ?? '—',
                'sku'          => $s->item?->sku ?? '—',
                'unit'         => $s->item?->unit?->code ?? '',
                'quantity'     => $s->quantity,
                'inbound_date' => $s->inbound_date?->format('d M Y'),
                'expiry_date'  => $s->expiry_date?->format('d M Y'),
                'lpn'          => $s->lpn,
            ];
        });

        return response()->json([
            'cell' => [
                'code'          => $cell->code,
                'label'         => $cell->label,
                'rack'          => $cell->rack?->code ?? '—',
                'zone'          => $cell->rack?->zone?->name ?? '—',
                'warehouse'     => $cell->rack?->zone?->warehouse?->name ?? '—',
                'status'        => $cell->status,
                'capacity_max'  => $cell->capacity_max,
                'capacity_used' => $cell->capacity_used,
                'utilization'   => $cell->utilization_percent,
                'category'      => $cell->dominantCategory?->name,
                'level'         => $cell->level,
                'column'        => $cell->column,
            ],
            'stocks' => $stocks,
        ]);
    }
}
