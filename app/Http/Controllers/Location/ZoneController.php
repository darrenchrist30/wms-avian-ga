<?php

namespace App\Http\Controllers\Location;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class ZoneController extends Controller
{
    public function index()
    {
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();
        return view('location.zones.index', compact('warehouses'));
    }

    public function create()
    {
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();
        return view('location.zones.form', [
            'typeForm'   => 'create',
            'data'       => null,
            'warehouses' => $warehouses,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'code'         => 'required|string|max:10',
            'name'         => 'required|string|max:100',
            'description'  => 'nullable|string|max:255',
            'pos_x'        => 'nullable|numeric',
            'pos_z'        => 'nullable|numeric',
            'is_active'    => 'boolean',
        ]);
        $exists = Zone::where('warehouse_id', $request->warehouse_id)
            ->where('code', strtoupper($request->code))->exists();
        if ($exists) {
            return back()->withInput()->withErrors(['code' => 'Kode zona sudah digunakan di warehouse ini.']);
        }
        DB::beginTransaction();
        try {
            Zone::create([
                'warehouse_id' => $request->warehouse_id,
                'code'         => strtoupper($request->code),
                'name'         => $request->name,
                'description'  => $request->description,
                'pos_x'        => $request->pos_x ?? 0,
                'pos_z'        => $request->pos_z ?? 0,
                'is_active'    => $request->boolean('is_active', true),
            ]);
            DB::commit();
            return redirect()->route('location.zones.index')->with('success', 'Zona berhasil ditambahkan.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withInput()->with('error', 'Gagal menyimpan zona. Silakan coba lagi.');
        }
    }

    public function show($id) { return redirect()->route('location.zones.index'); }

    public function edit($id)
    {
        $data       = Zone::withCount('racks')->findOrFail($id);
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();
        return view('location.zones.form', [
            'typeForm'   => 'edit',
            'data'       => $data,
            'warehouses' => $warehouses,
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = Zone::findOrFail($id);
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'code'         => 'required|string|max:10',
            'name'         => 'required|string|max:100',
            'description'  => 'nullable|string|max:255',
            'pos_x'        => 'nullable|numeric',
            'pos_z'        => 'nullable|numeric',
            'is_active'    => 'boolean',
        ]);
        $exists = Zone::where('warehouse_id', $request->warehouse_id)
            ->where('code', strtoupper($request->code))
            ->where('id', '!=', $id)->exists();
        if ($exists) {
            return back()->withInput()->withErrors(['code' => 'Kode zona sudah digunakan di warehouse ini.']);
        }
        DB::beginTransaction();
        try {
            $data->update([
                'warehouse_id' => $request->warehouse_id,
                'code'         => strtoupper($request->code),
                'name'         => $request->name,
                'description'  => $request->description,
                'pos_x'        => $request->pos_x ?? $data->pos_x,
                'pos_z'        => $request->pos_z ?? $data->pos_z,
                'is_active'    => $request->boolean('is_active', true),
            ]);
            DB::commit();
            return redirect()->route('location.zones.index')->with('success', 'Zona berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withInput()->with('error', 'Gagal memperbarui zona. Silakan coba lagi.');
        }
    }

    public function destroy($id)
    {
        $data = Zone::withCount('racks')->findOrFail($id);
        if ($data->racks_count > 0) {
            return response()->json(['status' => 'error', 'message' => 'Zona tidak dapat dihapus karena masih memiliki ' . $data->racks_count . ' rak.'], 422);
        }
        DB::beginTransaction();
        try {
            $data->delete();
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Zona berhasil dihapus.']);
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return response()->json(['status' => 'error', 'message' => 'Terjadi kesalahan pada server.'], 500);
        }
    }

    public function datatable(Request $request)
    {
        $query = Zone::with('warehouse')->withCount('racks');
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status);
        }
        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('warehouse_name', function ($row) {
                return $row->warehouse->name ?? '-';
            })
            ->addColumn('status_badge', function ($row) {
                return $row->is_active
                    ? '<span class="badge badge-success">Aktif</span>'
                    : '<span class="badge badge-secondary">Nonaktif</span>';
            })
            ->addColumn('action', function ($row) {
                $editUrl = route('location.zones.edit', $row->id);
                $html  = '<a href="' . $editUrl . '" class="btn btn-xs btn-warning" title="Edit"><i class="fas fa-edit"></i></a> ';
                $html .= '<button class="btn btn-xs btn-danger btnDel" data-id="' . $row->id . '" data-name="' . e($row->code) . '" title="Hapus"><i class="fas fa-trash"></i></button>';
                return $html;
            })
            ->rawColumns(['status_badge', 'action'])
            ->make(true);
    }
}
