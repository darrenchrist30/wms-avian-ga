<?php

namespace App\Http\Controllers;

use App\Models\Cell;
use App\Models\Rack;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Warehouse3DController extends Controller
{
    // ─── Warehouse IDs yang boleh diakses user ini ───────────────────────────
    private function authorizedWarehouseIds(): array
    {
        $user = auth()->user();
        if ($user->warehouse_id) {
            return [(int) $user->warehouse_id];
        }
        return Warehouse::where('is_active', true)->pluck('id')->map(fn($id) => (int) $id)->toArray();
    }

    private function isWarehouseAuthorized(int $warehouseId): bool
    {
        return in_array($warehouseId, $this->authorizedWarehouseIds(), true);
    }

    // ─── View utama 3D / grid visualisasi ───────────────────────────────────
    public function index(Request $request)
    {
        $warehouseId     = $request->input('warehouse_id');
        $highlightCellId = (int) $request->input('highlight_cell_id', 0);

        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();

        // Default: gudang sparepart; fallback ke gudang pertama
        $selectedWarehouse = $warehouseId
            ? $warehouses->find($warehouseId)
            : ($warehouses->first(fn($w) => stripos($w->name, 'sparepart') !== false) ?? $warehouses->first());

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
            $wid = $selectedWarehouse->id;
            $totalCells = Cell::whereHas('rack', fn($q) => $q->where('warehouse_id', $wid))->where('is_active', true)->count();
            $usedCells  = Cell::whereHas('rack', fn($q) => $q->where('warehouse_id', $wid))->where('is_active', true)->where('capacity_used', '>', 0)->count();

            $summary = [
                'total_racks'   => Rack::where('warehouse_id', $wid)->where('is_active', true)->count(),
                'total_cells'   => $totalCells,
                'total_sku'     => Stock::where('warehouse_id', $wid)->where('status', 'available')->distinct('item_id')->count('item_id'),
                'used_cells'    => $usedCells,
                'full_cells'    => Cell::whereHas('rack', fn($q) => $q->where('warehouse_id', $wid))->where('is_active', true)->where('status', 'full')->count(),
                'blocked_cells' => Cell::whereHas('rack', fn($q) => $q->where('warehouse_id', $wid))->where('is_active', true)->where('status', 'blocked')->count(),
            ];
            $summary['utilization'] = $totalCells > 0
                ? round(($usedCells / $totalCells) * 100, 1) : 0;
        }

        return view('warehouse3d.index', compact('warehouses', 'selectedWarehouse', 'summary', 'highlightCellId'));
    }

    // ─── JSON data grid visualisasi (per warehouse) ──────────────────────────
    public function data(Request $request)
    {
        $warehouseId = (int) $request->input('warehouse_id');

        if ($warehouseId && !$this->isWarehouseAuthorized($warehouseId)) {
            return response()->json(['error' => 'Akses ditolak.'], 403);
        }

        try {

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
            // stock_qty from aggregated $stockLoader (SUM of available quantities)
            $stockQty = (int) ($cell->stocks->first()?->qty ?? 0);
            return [
                'cell_id'       => $cell->id,
                'code'          => $cell->code,
                'display_code'  => $cell->physical_code,
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
                'stock_qty'     => $stockQty,
                'category'      => $cell->dominantCategory
                    ? ['name' => $cell->dominantCategory->name, 'color' => $cell->dominantCategory->color_code]
                    : null,
            ];
        };

        $racks = $parentRacks->map(function ($rack) use ($subRacks, $mapCell, $cellLoader, $stockLoader) {
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

                $regularCells = $rack->cells;

                // R12-R20 are physical racks/areas in the 3D layout. In the current
                // master data their cells exist but are inactive, so the normal
                // active-cell loader returns an empty collection and the object has
                // no clickable mesh. Load those cells as display-only fallback.
                if (in_array((string) $rack->code, ['12', '13', '14', '15', '16', '17', '18', '19', '20'], true) && $regularCells->isEmpty()) {
                    $regularCells = Cell::where('rack_id', $rack->id)
                        ->with([
                            'dominantCategory',
                            'stocks' => $stockLoader,
                        ])
                        ->orderBy('level')
                        ->orderBy('column')
                        ->get();
                }

                $regularMapped = collect($regularCells->map($mapCell)->all());
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

        } catch (\Throwable $e) {
            Log::error('[Warehouse3D] data() error', [
                'warehouse_id' => $warehouseId,
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error'   => 'Gagal memuat data gudang. Coba refresh halaman.',
                'detail'  => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        }
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
            $cells        = Cell::where('blok', $blok)->where('grup', $grup)->where('kolom', $kolom)->whereNotNull('baris')->where('is_active', true)->get();
            $barisCount   = $cells->count();
            $capUsed      = (int) $cells->sum('capacity_used');
            $capMax       = (int) ($cells->sum('capacity_max') ?: $barisCount * 20);
            $fullCount    = $cells->where('status', 'full')->count();
            $partialCount = $cells->where('status', 'partial')->count();
            $emptyCount   = $cells->filter(fn ($c) => (int) $c->capacity_used <= 0)->count();
            $util         = $capMax > 0 ? min(100, (int) floor($capUsed / $capMax * 100)) : 0;
            $status       = $capUsed <= 0
                ? 'available'
                : (($barisCount > 0 && $fullCount >= $barisCount) ? 'full' : 'partial');
            return [
                'kolom'         => $kolom,
                'label'         => "Kolom {$kolom}",
                'baris_rak'     => $barisRak,
                'baris_count'   => $barisCount,
                'cap_used'      => $capUsed,
                'cap_max'       => $capMax,
                'full_count'    => $fullCount,
                'partial_count' => $partialCount,
                'empty_count'   => $emptyCount,
                'over_capacity' => max(0, $capUsed - $capMax),
                'util_pct'      => $util,
                'status'        => $status,
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

        try {
            $cells = Cell::where('blok', $blok)
                ->where('grup', $grup)
                ->where('kolom', $kolom)
                ->where('is_active', true)
                ->whereNotNull('baris')
                ->orderBy('baris')
                ->with([
                    'stocks' => fn($q) => $q->where('status', 'available')->where('quantity', '>', 0)->with('item.unit')->orderBy('inbound_date'),
                    'dominantCategory',
                ])
                ->get();

            $levels = $cells->map(function ($cell) {
                $stocks  = $cell->stocks;
                $capUsed = (int) $cell->capacity_used;
                $capMax  = (int) ($cell->capacity_max ?: 100);
                $status  = $cell->status ?: ($capUsed <= 0 ? 'available' : ($capUsed >= $capMax ? 'full' : 'partial'));

                return [
                    'cell_id'      => $cell->id,
                    'code'         => $cell->code,
                    'display_code' => $cell->physical_code,
                    'baris'        => $cell->baris,
                    'status'       => $status,
                    'capacity_used'=> $capUsed,
                    'capacity_max' => $capMax,
                    'stocks'       => $stocks->map(fn($s) => [
                        'item_name'    => $s->item?->name ?? '-',
                        'sku'          => $s->item?->sku ?? '-',
                        'unit'         => $s->item?->unit?->code ?? '',
                        'quantity'     => $s->quantity,
                        'inbound_date' => $this->safeDateFormat($s->getRawOriginal('inbound_date')),
                    ])->values(),
                ];
            })->values();

            // Kategori dominan kolom = kategori terbanyak dari sel yang punya dominant_category
            $catCounts = $cells->filter(fn($c) => $c->dominantCategory)
                ->groupBy(fn($c) => $c->dominant_category_id)
                ->map->count();
            $dominantCatId   = $catCounts->isEmpty() ? null : $catCounts->sortDesc()->keys()->first();
            $dominantCatName = $dominantCatId
                ? $cells->first(fn($c) => $c->dominant_category_id === $dominantCatId)?->dominantCategory?->name
                : null;

            return response()->json([
                'blok'           => $blok,
                'grup'           => $grup,
                'baris_rak'      => $barisRak,
                'kolom'          => $kolom,
                'label'          => "Blok {$blok} – Grup {$grup} – Kolom {$kolom}",
                'dominant_category' => $dominantCatName,
                'levels'         => $levels,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Warehouse3D] columnDetail error', ['error' => $e->getMessage(), 'blok' => $blok, 'grup' => $grup, 'kolom' => $kolom]);
            return response()->json(['error' => 'Gagal memuat data: ' . $e->getMessage()], 500);
        }
    }

    private function grupToRackRow(string $grup): ?int
    {
        $map = array_flip(range('A', 'H'));
        return isset($map[$grup]) ? $map[$grup] + 1 : null;
    }

    private function safeDateFormat(?string $raw): ?string
    {
        if (!$raw || $raw === '0000-00-00' || $raw === '0000-00-00 00:00:00') {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($raw)->format('d M Y');
        } catch (\Throwable) {
            return null;
        }
    }

    // ─── Cell list berisi item (untuk highlight pencarian SKU) ──────────────
    public function cellsByItem(Request $request)
    {
        $q = trim($request->input('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $allowedIds = $this->authorizedWarehouseIds();
        $cells = Stock::where('status', 'available')
            ->where('quantity', '>', 0)
            ->whereIn('warehouse_id', $allowedIds)
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
        $cell->loadMissing('rack');
        if ($cell->rack && !$this->isWarehouseAuthorized((int) $cell->rack->warehouse_id)) {
            return response()->json(['error' => 'Akses ditolak.'], 403);
        }

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
                'display_code'  => $cell->physical_code,
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

    public function areaDetail(Request $request)
    {
        $rackCode = trim((string) $request->input('rack_code', ''));
        $label    = trim((string) $request->input('label', ''));

        if ($rackCode === '') {
            return response()->json(['error' => 'rack_code wajib diisi.'], 422);
        }

        $allowedIds = $this->authorizedWarehouseIds();
        $rack = Rack::with([
                'warehouse',
                'cells' => fn ($q) => $q->orderBy('level')->orderBy('column'),
                'cells.stocks' => fn ($q) => $q->where('status', 'available')
                    ->where('quantity', '>', 0)
                    ->with('item.unit')
                    ->orderBy('inbound_date', 'asc'),
            ])
            ->where('code', $rackCode)
            ->whereIn('warehouse_id', $allowedIds)
            ->firstOrFail();

        $cells = $rack->cells;
        $capacityUsed = (int) $cells->sum('capacity_used');
        $capacityMax  = (int) $cells->sum('capacity_max');
        $utilization  = $capacityMax > 0 ? round($capacityUsed / $capacityMax * 100, 1) : 0;
        $status       = $capacityUsed <= 0 ? 'available' : ($capacityUsed >= $capacityMax ? 'full' : 'partial');

        $stocks = $cells->flatMap(fn ($cell) => $cell->stocks->map(fn ($stock) => [
            'item_name'    => $stock->item?->name ?? '-',
            'sku'          => $stock->item?->sku ?? '-',
            'unit'         => $stock->item?->unit?->code ?? '',
            'quantity'     => $stock->quantity,
            'inbound_date' => $stock->inbound_date?->format('d M Y'),
            'cell_code'    => $cell->code,
        ]))->values();

        return response()->json([
            'area' => [
                'rack_code'     => $rack->code,
                'label'         => $label !== '' ? $label : ($rack->name ?: "Area {$rack->code}"),
                'warehouse'     => $rack->warehouse?->name ?? '-',
                'status'        => $status,
                'capacity_used' => $capacityUsed,
                'capacity_max'  => $capacityMax,
                'utilization'   => $utilization,
            ],
            'stocks' => $stocks,
        ]);
    }
}
