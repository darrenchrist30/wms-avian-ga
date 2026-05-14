<?php

namespace App\Http\Controllers;

use App\Models\Cell;
use App\Models\Rack;
use App\Models\Stock;
use App\Models\Warehouse;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Warehouse3DController extends Controller
{
    // ─── View utama 3D / grid visualisasi ───────────────────────────────────
    public function index(Request $request)
    {
        $warehouseId     = $request->input('warehouse_id');
        $highlightCellId = (int) $request->input('highlight_cell_id', 0);

        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();

        // Default: gudang pertama; jika ada highlight_cell_id, auto-pilih warehouse-nya
        $selectedWarehouse = $warehouseId
            ? $warehouses->find($warehouseId)
            : $warehouses->first();

        if ($highlightCellId && !$warehouseId) {
            $hCell = Cell::with('rack.zone')->find($highlightCellId);
            $detectedWid = $hCell?->rack?->zone?->warehouse_id;
            if ($detectedWid) {
                $selectedWarehouse = $warehouses->find($detectedWid);
            }
        }

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

        return view('warehouse3d.index', compact('warehouses', 'selectedWarehouse', 'summary', 'highlightCellId'));
    }

    // ─── JSON data grid visualisasi (per warehouse) ──────────────────────────
    public function data(Request $request)
    {
        $warehouseId = $request->input('warehouse_id');

        $cellLoader = function ($q) {
            $q->where('is_active', true)->orderBy('level')->orderBy('column');
        };
        $stockLoader = function ($q) {
            $q->where('status', 'available')->select('cell_id', DB::raw('SUM(quantity) as qty'))
              ->groupBy('cell_id');
        };

        $zones = Zone::with([
            'racks'                   => fn($q) => $q->orderBy('code'),
            'racks.cells'             => $cellLoader,
            'racks.cells.dominantCategory',
            'racks.cells.stocks'      => $stockLoader,
        ])
        ->where('warehouse_id', $warehouseId)
        ->orderBy('name')
        ->get();

        // Sub-racks: codes matching /^\d+[A-G]$/ (mspart layout racks like "1A", "4G")
        $subRacks = Rack::whereHas('zone', fn($q) => $q->where('warehouse_id', $warehouseId))
            ->whereRaw("code REGEXP '^[0-9]+[A-G]$'")
            ->with([
                'cells'              => $cellLoader,
                'cells.dominantCategory',
                'cells.stocks'       => $stockLoader,
            ])
            ->get()
            ->groupBy(fn($r) => preg_replace('/[A-G]$/', '', $r->code));  // group by blok number

        $mapCell = function ($cell) {
            $utilPct = $cell->capacity_max > 0
                ? round($cell->capacity_used / $cell->capacity_max * 100)
                : 0;
            return [
                'cell_id'       => $cell->id,
                'code'          => $cell->code,
                'level'         => $cell->level,
                'column'        => $cell->column,
                'blok'          => $cell->blok,
                'grup'          => $cell->grup,
                'kolom'         => $cell->kolom,
                'baris'         => $cell->baris,
                'status'        => $cell->status,
                'capacity_max'  => $cell->capacity_max,
                'capacity_used' => $cell->capacity_used,
                'utilization'   => $utilPct,
                'category'      => $cell->dominantCategory
                    ? ['name' => $cell->dominantCategory->name, 'color' => $cell->dominantCategory->color_code]
                    : null,
            ];
        };

        $data = $zones->map(function ($zone) use ($subRacks, $mapCell) {
            $racks = $zone->racks->map(function ($rack) use ($subRacks, $mapCell) {
                // Merge sub-rack cells into parent rack
                $mspartCells = collect();
                if ($subRacks->has($rack->code)) {
                    foreach ($subRacks->get($rack->code) as $subRack) {
                        $mspartCells = $mspartCells->merge($subRack->cells);
                    }
                }

                $allCells = $rack->cells->map($mapCell)
                    ->merge($mspartCells->map($mapCell))
                    ->values();

                return [
                    'rack_id'       => $rack->id,
                    'rack_code'     => $rack->code,
                    'pos_x'         => (float) ($rack->pos_x ?? 0),
                    'pos_z'         => (float) ($rack->pos_z ?? 0),
                    'rotation_y'    => (float) ($rack->rotation_y ?? 0),
                    'total_levels'  => (int)   ($rack->total_levels ?? 7),
                    'total_columns' => (int)   ($rack->total_columns ?? 1),
                    'cells'         => $allCells,
                ];
            });

            return [
                'zone_id'   => $zone->id,
                'zone_name' => $zone->name,
                'zone_code' => $zone->code,
                'zone_type' => $zone->zone_type ?? 'storage',
                'pos_x'     => (float) ($zone->pos_x ?? 0),
                'pos_z'     => (float) ($zone->pos_z ?? 0),
                'racks'     => $racks,
            ];
        });

        return response()->json($data);
    }

    // ─── Detail kolom mspart (semua baris dalam satu kolom fisik) ───────────
    public function columnDetail(Request $request)
    {
        $blok  = (int) $request->input('blok');
        $grup  = strtoupper(trim($request->input('grup', '')));
        $kolom = (int) $request->input('kolom');

        if (!$blok || !$grup || !$kolom) {
            return response()->json(['error' => 'blok, grup, kolom wajib diisi.'], 422);
        }

        $cells = Cell::where('blok', $blok)
            ->where('grup', $grup)
            ->where('kolom', $kolom)
            ->where('is_active', true)
            ->orderBy('baris')
            ->with([
                'stocks' => fn($q) => $q->where('status', 'available')->with('item.unit')->orderBy('inbound_date'),
            ])
            ->get();

        $result = $cells->map(function ($cell) {
            return [
                'cell_id'      => $cell->id,
                'code'         => $cell->code,
                'baris'        => $cell->baris,
                'status'       => $cell->status,
                'capacity_used'=> $cell->capacity_used,
                'capacity_max' => $cell->capacity_max,
                'stocks'       => $cell->stocks->map(fn($s) => [
                    'item_name'    => $s->item?->name ?? '—',
                    'sku'          => $s->item?->sku ?? '—',
                    'unit'         => $s->item?->unit?->code ?? '',
                    'quantity'     => $s->quantity,
                    'inbound_date' => $s->inbound_date?->format('d M Y'),
                ]),
            ];
        });

        return response()->json([
            'blok'   => $blok,
            'grup'   => $grup,
            'kolom'  => $kolom,
            'label'  => "Kolom {$blok}-{$grup}-{$kolom}",
            'levels' => $result,
        ]);
    }

    // ─── Cell list berisi item (untuk highlight pencarian SKU) ──────────────
    public function cellsByItem(Request $request)
    {
        $q = trim($request->input('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $cells = Stock::where('status', 'available')
            ->where('quantity', '>', 0)
            ->whereHas('item', fn($iq) =>
                $iq->where('sku', 'like', "%{$q}%")
                   ->orWhere('name', 'like', "%{$q}%")
            )
            ->with(['cell', 'item'])
            ->get()
            ->groupBy('cell_id')
            ->map(fn($group) => [
                'cell_id'  => $group->first()->cell_id,
                'code'     => $group->first()->cell?->code ?? '—',
                'quantity' => $group->sum('quantity'),
                'item'     => $group->first()->item?->name ?? '—',
            ])
            ->values();

        return response()->json($cells);
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
                'blok'          => $cell->blok,
                'grup'          => $cell->grup,
                'kolom'         => $cell->kolom,
                'baris'         => $cell->baris,
            ],
            'stocks' => $stocks,
        ]);
    }
}
