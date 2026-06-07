<?php

namespace App\Http\Controllers\Location;

use App\Http\Controllers\Controller;
use App\Models\Cell;
use App\Models\ItemCategory;
use App\Models\Rack;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class RackController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,supervisor')->only(['create', 'store', 'edit', 'update', 'destroy']);
    }

    public function index()
    {
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();
        $defaultWarehouseId = Rack::where('racks.is_active', true)
            ->join('warehouses', 'racks.warehouse_id', '=', 'warehouses.id')
            ->where('warehouses.is_active', true)
            ->value('racks.warehouse_id');
        return view('location.racks.index', compact('warehouses', 'defaultWarehouseId'));
    }

    public function create()
    {
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();
        $categories = ItemCategory::where('is_active', true)->orderBy('name')->get();
        return view('location.racks.form', [
            'typeForm'            => 'create',
            'data'                => null,
            'warehouses'          => $warehouses,
            'categories'          => $categories,
            'selectedWarehouseId' => request('warehouse_id'),
            'selectedPosX'        => request('pos_x'),
            'selectedPosZ'        => request('pos_z'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'warehouse_id'          => 'required|exists:warehouses,id',
            'dominant_category_id'  => 'nullable|exists:item_categories,id',
            'code'                  => 'required|string|max:20',
            'name'                  => 'nullable|string|max:100',
            'total_levels'          => 'required|integer|min:1|max:26',
            'total_columns'         => 'required|integer|min:1|max:20',
            'pos_x'                 => 'nullable|numeric',
            'pos_z'                 => 'nullable|numeric',
            'rotation_y'            => 'nullable|numeric',
            'is_active'             => 'boolean',
        ]);
        $exists = Rack::where('warehouse_id', $request->warehouse_id)
            ->where('code', strtoupper($request->code))->exists();
        if ($exists) {
            return back()->withInput()->withErrors(['code' => 'Kode rak sudah digunakan di gudang ini.']);
        }
        DB::beginTransaction();
        try {
            $rack = Rack::create([
                'warehouse_id'         => $request->warehouse_id,
                'dominant_category_id' => $request->dominant_category_id,
                'code'                 => strtoupper($request->code),
                'name'                 => $request->name,
                'total_levels'         => $request->total_levels,
                'total_columns'        => $request->total_columns,
                'pos_x'                => $request->pos_x ?? 0,
                'pos_z'                => $request->pos_z ?? 0,
                'rotation_y'           => $request->rotation_y ?? 0,
                'is_active'            => $request->boolean('is_active', true),
            ]);
            // Generate sel per level × kolom
            // 1 kolom → kode: R-01-A, R-01-B   (tanpa suffix angka, backward-compatible)
            // >1 kolom → kode: R-01-A1, R-01-A2, R-01-B1, ...
            $cols = $rack->total_columns;
            for ($level = 1; $level <= $rack->total_levels; $level++) {
                $letter = chr(64 + $level);
                for ($col = 1; $col <= $cols; $col++) {
                    $cellCode = $cols === 1
                        ? $rack->code . '-' . $letter
                        : $rack->code . '-' . $letter . $col;
                    Cell::create([
                        'rack_id'       => $rack->id,
                        'code'          => $cellCode,
                        'level'         => $level,
                        'column'        => $col,
                        'capacity_max'  => 100,
                        'capacity_used' => 0,
                        'status'        => 'available',
                        'is_active'     => true,
                    ]);
                }
            }
            DB::commit();
            $totalCells = $rack->total_levels * $cols;
            $lastLetter = chr(64 + $rack->total_levels);
            $successMsg = $cols === 1
                ? 'Rak ' . strtoupper($request->code) . ' berhasil ditambahkan beserta ' . $totalCells . ' sel (A–' . $lastLetter . ').'
                : 'Rak ' . strtoupper($request->code) . ' berhasil ditambahkan beserta ' . $totalCells . ' sel (' . $rack->total_levels . ' level × ' . $cols . ' kolom).';
            if ($request->filled('from_warehouse')) {
                return redirect()->route('location.warehouses.edit', $request->from_warehouse)
                    ->with('success', $successMsg);
            }
            return redirect()->route('location.racks.index')->with('success', $successMsg);
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withInput()->with('error', 'Gagal menyimpan rak. Silakan coba lagi.');
        }
    }

    public function show($id)
    {
        $rack = Rack::with([
            'warehouse',
            'dominantCategory',
            'cells' => fn($q) => $q->orderBy('level')->orderBy('column'),
        ])->withCount('cells')->findOrFail($id);

        // Susun grid: [level][column] => cell
        $cellGrid = [];
        foreach ($rack->cells as $cell) {
            $cellGrid[$cell->level][$cell->column] = $cell;
        }

        $levels  = range(1, max(1, $rack->total_levels));
        $columns = range(1, max(1, $rack->total_columns));

        return view('location.racks.show', compact('rack', 'cellGrid', 'levels', 'columns'));
    }

    public function edit($id)
    {
        $data       = Rack::withCount('cells')->findOrFail($id);
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();
        $categories = ItemCategory::where('is_active', true)->orderBy('name')->get();
        return view('location.racks.form', ['typeForm' => 'edit', 'data' => $data, 'warehouses' => $warehouses, 'categories' => $categories]);
    }

    public function update(Request $request, $id)
    {
        $rack = Rack::findOrFail($id);
        $request->validate([
            'warehouse_id'         => 'required|exists:warehouses,id',
            'dominant_category_id' => 'nullable|exists:item_categories,id',
            'code'                 => 'required|string|max:20',
            'name'                 => 'nullable|string|max:100',
            'pos_x'                => 'nullable|numeric',
            'pos_z'                => 'nullable|numeric',
            'rotation_y'           => 'nullable|numeric',
            'is_active'            => 'boolean',
        ]);
        $exists = Rack::where('warehouse_id', $request->warehouse_id)
            ->where('code', strtoupper($request->code))
            ->where('id', '!=', $id)->exists();
        if ($exists) {
            return back()->withInput()->withErrors(['code' => 'Kode rak sudah digunakan di gudang ini.']);
        }
        DB::beginTransaction();
        try {
            $rack->update([
                'warehouse_id'         => $request->warehouse_id,
                'dominant_category_id' => $request->dominant_category_id,
                'code'                 => strtoupper($request->code),
                'name'                 => $request->name,
                'pos_x'                => $request->pos_x ?? $rack->pos_x,
                'pos_z'                => $request->pos_z ?? $rack->pos_z,
                'rotation_y'           => $request->rotation_y ?? $rack->rotation_y,
                'is_active'            => $request->boolean('is_active', true),
            ]);
            DB::commit();
            return redirect()->route('location.racks.show', $id)->with('success', 'Rak berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withInput()->with('error', 'Gagal memperbarui rak. Silakan coba lagi.');
        }
    }

    public function destroy($id)
    {
        $rack = Rack::withCount('cells')->findOrFail($id);
        $hasStock = $rack->cells()->whereHas('stocks')->exists();
        if ($hasStock) {
            return response()->json(['status' => 'error', 'message' => 'Rak tidak dapat dihapus karena masih ada sel yang memiliki stok aktif.'], 422);
        }
        DB::beginTransaction();
        try {
            $rack->cells()->delete();
            $rack->delete();
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Rak beserta ' . $rack->cells_count . ' sel berhasil dihapus.']);
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return response()->json(['status' => 'error', 'message' => 'Terjadi kesalahan pada server.'], 500);
        }
    }

    public function select2(Request $request)
    {
        $results = Rack::with('warehouse')
            ->where('is_active', true)
            ->when($request->filled('q'), fn($q) => $q->where(function ($q2) use ($request) {
                $q2->where('code', 'like', '%' . $request->q . '%')
                   ->orWhere('name', 'like', '%' . $request->q . '%');
            }))
            ->when($request->filled('warehouse_id'), fn($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->orderBy('code')
            ->limit(100)
            ->get()
            ->map(fn($r) => [
                'id'   => $r->id,
                'text' => $r->code . ' — ' . ($r->warehouse->name ?? '-'),
            ]);

        return response()->json(['results' => $results]);
    }

    public function datatable(Request $request)
    {
        $query = Rack::with([
            'warehouse',
            'cells' => fn($q) => $q
                ->where('is_active', true)
                ->whereNotNull('dominant_category_id')
                ->with('dominantCategory'),
        ])->withCount(['cells' => fn($q) => $q->where('is_active', true)->whereNotNull('baris')])
          ->where('is_active', true);
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status);
        }
        return DataTables::of($query)
            ->addIndexColumn()
            ->orderColumn('code', 'CAST(code AS UNSIGNED) $1, code $1')
            ->addColumn('lokasi', function ($row) {
                $wh = $row->warehouse->name ?? '-';
                return '<span style="color:#212529;font-size:13px;">' . e($wh) . '</span>';
            })
            ->addColumn('kategori', function ($row) {
                $categorizedCells = $row->cells->filter(fn($cell) => $cell->dominantCategory);
                if ($categorizedCells->isEmpty()) {
                    return '<span class="text-muted">-</span>';
                }

                $categoryGroups = $categorizedCells->groupBy('dominant_category_id')
                    ->map(fn($cells) => [
                        'count' => $cells->count(),
                        'category' => $cells->first()->dominantCategory,
                    ])
                    ->sortByDesc('count');

                $dominant = $categoryGroups->first();
                $category = $dominant['category'];
                $color = $category->color_code ?? '#6c757d';
                $name = e($category->name);

                return '<span class="badge" style="background:' . $color . ';color:#fff;">' . $name . '</span>';
            })
            ->addColumn('status_badge', function ($row) {
                return $row->is_active
                    ? '<span class="badge badge-success">Aktif</span>'
                    : '<span class="badge badge-secondary">Nonaktif</span>';
            })
            ->addColumn('action', function ($row) {
                $showUrl = route('location.racks.show', $row->id);
                $html  = '<a href="' . $showUrl . '" class="btn btn-xs btn-primary" title="Detail"><i class="fas fa-eye"></i></a> ';
                if (!auth()->user()->isOperator()) {
                    $html .= '<button class="btn btn-xs btn-danger btnDel" data-id="' . $row->id . '" data-name="' . e($row->code) . '" title="Hapus"><i class="fas fa-trash"></i></button>';
                }
                return $html;
            })
            ->rawColumns(['lokasi', 'kategori', 'status_badge', 'action'])
            ->make(true);
    }
}
