<?php

namespace App\Http\Controllers\Location;

use App\Http\Controllers\Controller;
use App\Models\Cell;
use App\Models\Rack;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class WarehouseController extends Controller
{
    public function index()
    {
        return view('location.warehouses.index');
    }

    public function create()
    {
        return view('location.warehouses.form', ['typeForm' => 'create', 'data' => null]);
    }

    public function store(Request $request)
    {
        $rules = [
            'code'      => 'required|string|max:20|unique:warehouses,code',
            'name'      => 'required|string|max:100',
            'address'   => 'nullable|string|max:500',
            'pic'       => 'nullable|string|max:100',
            'phone'     => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ];

        if ($request->boolean('generate_layout')) {
            $rules += [
                'rack_count'       => 'required|integer|min:1|max:100',
                'rack_prefix'      => 'required|string|max:10|alpha_dash',
                'rack_levels'      => 'required|integer|min:1|max:7',
                'rack_columns'     => 'required|integer|min:1|max:10',
                'rack_layout_cols' => 'required|integer|min:1|max:20',
                'default_capacity' => 'required|integer|min:1|max:9999',
            ];
        }

        $request->validate($rules);

        DB::beginTransaction();
        try {
            $warehouse = Warehouse::create([
                'code'      => strtoupper($request->code),
                'name'      => $request->name,
                'address'   => $request->address,
                'pic'       => $request->pic,
                'phone'     => $request->phone,
                'is_active' => $request->boolean('is_active', true),
            ]);

            $rackCount = 0;
            $cellCount = 0;

            if ($request->boolean('generate_layout')) {
                $rackTotal  = (int) $request->rack_count;
                $prefix     = strtoupper(trim($request->rack_prefix));
                $levels     = (int) $request->rack_levels;
                $columns    = (int) $request->rack_columns;
                $layoutCols = (int) $request->rack_layout_cols;
                $capacity   = (int) $request->default_capacity;

                for ($i = 1; $i <= $rackTotal; $i++) {
                    $rackCode = $prefix . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
                    $colIdx   = ($i - 1) % $layoutCols;
                    $rowIdx   = (int) floor(($i - 1) / $layoutCols);

                    $rack = Rack::create([
                        'warehouse_id'  => $warehouse->id,
                        'code'          => $rackCode,
                        'name'          => null,
                        'total_levels'  => $levels,
                        'total_columns' => $columns,
                        'pos_x'         => $colIdx * 2.5,
                        'pos_z'         => $rowIdx * 3.5,
                        'rotation_y'    => 0,
                        'is_active'     => true,
                    ]);

                    for ($level = 1; $level <= $levels; $level++) {
                        $letter = chr(64 + $level);
                        for ($col = 1; $col <= $columns; $col++) {
                            $cellCode = $columns > 1
                                ? $rackCode . '-' . $letter . $col
                                : $rackCode . '-' . $letter;

                            Cell::create([
                                'rack_id'       => $rack->id,
                                'code'          => $cellCode,
                                'level'         => $level,
                                'column'        => $col,
                                'capacity_max'  => $capacity,
                                'capacity_used' => 0,
                                'status'        => 'available',
                                'is_active'     => true,
                            ]);
                            $cellCount++;
                        }
                    }
                    $rackCount++;
                }
            }

            DB::commit();

            $msg = 'Warehouse ' . strtoupper($request->code) . ' berhasil ditambahkan.';
            if ($rackCount > 0) {
                $msg .= " Layout otomatis: {$rackCount} rak dan {$cellCount} sel berhasil dibuat.";
            }
            return redirect()->route('location.warehouses.index')->with('success', $msg);

        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withInput()->with('error', 'Gagal menyimpan warehouse: ' . $e->getMessage());
        }
    }

    public function show($id) { return redirect()->route('location.warehouses.index'); }

    public function edit($id)
    {
        $data = Warehouse::withCount('racks')->findOrFail($id);
        return view('location.warehouses.form', ['typeForm' => 'edit', 'data' => $data]);
    }

    public function update(Request $request, $id)
    {
        $warehouse = Warehouse::findOrFail($id);
        $request->validate([
            'code'      => 'required|string|max:20|unique:warehouses,code,' . $id,
            'name'      => 'required|string|max:100',
            'address'   => 'nullable|string|max:500',
            'pic'       => 'nullable|string|max:100',
            'phone'     => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);
        DB::beginTransaction();
        try {
            $warehouse->update([
                'code'      => strtoupper($request->code),
                'name'      => $request->name,
                'address'   => $request->address,
                'pic'       => $request->pic,
                'phone'     => $request->phone,
                'is_active' => $request->boolean('is_active', true),
            ]);
            DB::commit();
            return redirect()->route('location.warehouses.index')->with('success', 'Warehouse berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withInput()->with('error', 'Gagal memperbarui warehouse. Silakan coba lagi.');
        }
    }

    public function destroy($id)
    {
        $warehouse = Warehouse::withCount('racks')->findOrFail($id);
        if ($warehouse->racks_count > 0) {
            return response()->json(['status' => 'error', 'message' => 'Warehouse tidak dapat dihapus karena masih memiliki ' . $warehouse->racks_count . ' rak.'], 422);
        }
        DB::beginTransaction();
        try {
            $warehouse->delete();
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Warehouse berhasil dihapus.']);
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return response()->json(['status' => 'error', 'message' => 'Terjadi kesalahan pada server.'], 500);
        }
    }

    public function datatable(Request $request)
    {
        $query = Warehouse::withCount('racks');
        if ($request->filled('status')) {
            $query->where('is_active', $request->status);
        }
        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('status_badge', function ($row) {
                return $row->is_active
                    ? '<span class="badge badge-success">Aktif</span>'
                    : '<span class="badge badge-secondary">Nonaktif</span>';
            })
            ->addColumn('action', function ($row) {
                if (auth()->user()->isOperator()) return '-';
                $editUrl = route('location.warehouses.edit', $row->id);
                $html  = '<a href="' . $editUrl . '" class="btn btn-xs btn-warning" title="Edit"><i class="fas fa-edit"></i></a> ';
                $html .= '<button class="btn btn-xs btn-danger btnDel" data-id="' . $row->id . '" data-name="' . e($row->name) . '" title="Hapus"><i class="fas fa-trash"></i></button>';
                return $html;
            })
            ->rawColumns(['status_badge', 'action'])
            ->make(true);
    }
}
