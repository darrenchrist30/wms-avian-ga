<?php

namespace App\Http\Controllers;

use App\Models\Cell;
use App\Models\Rack;
use App\Models\Stock;
use App\Models\Warehouse;
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
            $hCell = Cell::with('rack')->find($highlightCellId);
            $detectedWid = $hCell?->rack?->warehouse_id;
            if ($detectedWid) {
                $selectedWarehouse = $warehouses->find($detectedWid);
            }
        }

        // Summary utilisasi
        $summary = null;
        if ($selectedWarehouse) {
            $summary = [
                'total_racks' => Rack::where('warehouse_id', $selectedWarehouse->id)->where('is_active', true)->count(),
                'total_cells' => Cell::whereHas('rack', fn($q) => $q->where('warehouse_id', $selectedWarehouse->id))->where('is_active', true)->count(),
                'used_cells'  => Cell::whereHas('rack', fn($q) => $q->where('warehouse_id', $selectedWarehouse->id))
                                    ->where('is_active', true)
                                    ->where('capacity_used', '>', 0)->count(),
                'full_cells'  => Cell::whereHas('rack', fn($q) => $q->where('warehouse_id', $selectedWarehouse->id))
                                    ->where('is_active', true)
                                    ->where('status', 'full')->count(),
                'blocked_cells' => Cell::whereHas('rack', fn($q) => $q->where('warehouse_id', $selectedWarehouse->id))
                                    ->where('is_active', true)
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

        $warehouse = Warehouse::find($warehouseId);
        $parentRacks = Rack::where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->whereRaw("code NOT REGEXP '^[0-9]+[A-H]$'")
            ->with([
                'cells'              => $cellLoader,
                'cells.dominantCategory',
                'cells.stocks'       => $stockLoader,
            ])
            ->orderBy('code')
            ->get();

        // Sub-racks: codes matching /^\d+[A-H]$/ (mspart layout racks like "1A", "4G", "3H")
        $subRacks = Rack::where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->whereRaw("code REGEXP '^[0-9]+[A-H]$'")
            ->with([
                'cells'              => $cellLoader,
                'cells.dominantCategory',
                'cells.stocks'       => $stockLoader,
            ])
            ->get()
            ->groupBy(fn($r) => preg_replace('/[A-H]$/', '', $r->code));  // group by blok number

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

        $racks = $parentRacks->map(function ($rack) use ($subRacks, $mapCell) {
                // Kumpulkan sub-rack cells dari (blok, grup) yang sama dengan rack induk.
                // Pakai base Collection bukan Eloquent Collection agar bisa di-merge dengan
                // array hasil $mapCell tanpa error "Call to getKey() on array".
                $mspartCells = collect();
                if ($subRacks->has($rack->code)) {
                    foreach ($subRacks->get($rack->code) as $subRack) {
                        foreach ($subRack->cells as $cell) {
                            $mspartCells->push($cell);
                        }
                    }
                }

                $regularMapped = collect($rack->cells->map($mapCell)->all());
                $mspartMapped  = collect($mspartCells->map($mapCell)->all());
                $allCells      = $regularMapped->merge($mspartMapped)->values();

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

        $data = [[
            'warehouse_id'   => $warehouse?->id,
            'warehouse_name' => $warehouse?->name ?? 'Warehouse',
            'pos_x'          => 0,
            'pos_z'          => 0,
            'racks'          => $racks,
        ]];

        return response()->json($data);
    }

    // ─── Detail grup mspart (semua kolom dalam satu blok-grup) ─────────────
    public function grupDetail(Request $request)
    {
        $blok = (int) $request->input('blok');
        $grup = strtoupper(trim($request->input('grup', '')));
        $barisRak = $this->grupToRackRow($grup);

        if (!$blok || !$grup) {
            return response()->json(['error' => 'blok dan grup wajib diisi.'], 422);
        }

        $columns = collect(range(1, 7))->map(function ($kolom) use ($blok, $grup, $barisRak) {
            $cells   = Cell::where('blok', $blok)->where('grup', $grup)->where('kolom', $kolom)->where('is_active', true)->get();
            $cellIds = $cells->pluck('id');
            $capUsed = $cellIds->isEmpty()
                ? 0
                : Stock::whereIn('cell_id', $cellIds)->where('status', 'available')->where('quantity', '>', 0)->count();
            $capMax  = (int) ($cells->max('capacity_max') ?: 20);
            $util    = $capMax > 0 ? min(100, round($capUsed / $capMax * 100)) : 0;
            $full    = $capUsed > 0 && $capUsed >= $capMax ? 1 : 0;
            $partial = $capUsed > 0 && $capUsed < $capMax ? 1 : 0;
            return [
                'kolom'    => $kolom,
                'label'    => "K{$kolom}",
                'baris_rak'=> $barisRak,
                'total'    => 1,
                'full'     => $full,
                'partial'  => $partial,
                'empty'    => $capUsed > 0 ? 0 : 1,
                'util_pct' => $util,
                'cap_used' => $capUsed,
                'cap_max'  => $capMax,
            ];
        });

        return response()->json([
            'blok'    => $blok,
            'grup'    => $grup,
            'baris_rak' => $barisRak,
            'label'   => "Blok {$blok} – Grup {$grup} – Baris {$barisRak}",
            'columns' => $columns,
        ]);
    }

    // ─── Detail kolom mspart (semua baris dalam satu kolom fisik) ───────────
    // Mengembalikan 9 entri (satu per baris) untuk satu (blok, grup, kolom),
    // bukan agregat. Setiap entri memakai cell.code asli dari DB (1-A-1-2, dst).
    public function columnDetail(Request $request)
    {
        $blok  = (int) $request->input('blok');
        $grup  = strtoupper(trim($request->input('grup', '')));
        $kolom = (int) $request->input('kolom');
        $barisRak = $this->grupToRackRow($grup);

        if (!$blok || !$grup || !$kolom) {
            return response()->json(['error' => 'blok, grup, kolom wajib diisi.'], 422);
        }

        $cells = Cell::where('blok', $blok)
            ->where('grup', $grup)
            ->where('kolom', $kolom)
            ->where('is_active', true)
            ->orderBy('baris')
            ->with([
                'stocks' => fn($q) => $q->where('status', 'available')->where('quantity', '>', 0)->with('item.unit')->orderBy('inbound_date'),
            ])
            ->get();

        $levels = $cells->map(function ($cell) {
            $stocks  = $cell->stocks;
            $capUsed = (int) $cell->capacity_used;
            $capMax  = (int) ($cell->capacity_max ?: 20);
            $status  = $cell->status ?: ($capUsed <= 0 ? 'available' : ($capUsed >= $capMax ? 'full' : 'partial'));

            return [
                'cell_id'      => $cell->id,
                'code'         => $cell->code,                      // ← code asli DB: "1-A-1-2"
                'baris'        => $cell->baris,
                'status'       => $status,
                'capacity_used'=> $capUsed,
                'capacity_max' => $capMax,
                'stocks'       => $stocks->map(fn($s) => [
                    'item_name'    => $s->item?->name ?? '-',
                    'sku'          => $s->item?->sku ?? '-',
                    'unit'         => $s->item?->unit?->code ?? '',
                    'quantity'     => $s->quantity,
                    'inbound_date' => $s->inbound_date?->format('d M Y'),
                ])->values(),
            ];
        })->values();

        return response()->json([
            'blok'      => $blok,
            'grup'      => $grup,
            'baris_rak' => $barisRak,
            'kolom'     => $kolom,
            'label'     => "Blok {$blok} – Grup {$grup} – Kolom {$kolom}",
            'levels'    => $levels,
        ]);
    }

    private function grupToRackRow(string $grup): ?int
    {
        $map = array_flip(range('A', 'H'));
        return isset($map[$grup]) ? $map[$grup] + 1 : null;
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
            'rack.warehouse',
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
                'warehouse'     => $cell->rack?->warehouse?->name ?? '—',
                'status'        => $cell->status,
                'capacity_max'  => $cell->capacity_max,
                'capacity_used' => $cell->capacity_used,
                'utilization'   => $cell->utilization_percent,
                'category'      => $cell->dominantCategory?->name,
                'level'         => $cell->level,
                'column'        => $cell->column,
                'blok'          => $cell->blok,
                'grup'          => $cell->grup,
                'baris_rak'     => $cell->grup ? $this->grupToRackRow(strtoupper($cell->grup)) : null,
                'kolom'         => $cell->kolom,
                'baris'         => $cell->baris,
            ],
            'stocks' => $stocks,
        ]);
    }
}
