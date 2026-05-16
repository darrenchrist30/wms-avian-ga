<?php

namespace App\Http\Controllers\Location;

use App\Http\Controllers\Controller;
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
        $request->validate([
            'code'      => 'required|string|max:20|unique:warehouses,code',
            'name'      => 'required|string|max:100',
            'address'   => 'nullable|string|max:500',
            'pic'       => 'nullable|string|max:100',
            'phone'     => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);
        DB::beginTransaction();
        try {
            Warehouse::create([
                'code'      => strtoupper($request->code),
                'name'      => $request->name,
                'address'   => $request->address,
                'pic'       => $request->pic,
                'phone'     => $request->phone,
                'is_active' => $request->boolean('is_active', true),
            ]);
            DB::commit();
            return redirect()->route('location.warehouses.index')->with('success', 'Warehouse berhasil ditambahkan.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withInput()->with('error', 'Gagal menyimpan warehouse. Silakan coba lagi.');
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
                $editUrl = route('location.warehouses.edit', $row->id);
                $html  = '<a href="' . $editUrl . '" class="btn btn-xs btn-warning" title="Edit"><i class="fas fa-edit"></i></a> ';
                $html .= '<button class="btn btn-xs btn-danger btnDel" data-id="' . $row->id . '" data-name="' . e($row->name) . '" title="Hapus"><i class="fas fa-trash"></i></button>';
                return $html;
            })
            ->rawColumns(['status_badge', 'action'])
            ->make(true);
    }
}
