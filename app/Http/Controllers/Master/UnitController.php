<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class UnitController extends Controller
{
    public function index()
    {
        return view('master.units.index');
    }

    public function create()
    {
        return view('master.units.form', ['typeForm' => 'create', 'data' => null]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'code'        => 'required|string|max:20|unique:units,code',
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'is_active'   => 'boolean',
        ]);
        DB::beginTransaction();
        try {
            Unit::create([
                'code'        => strtoupper($request->code),
                'name'        => $request->name,
                'description' => $request->description,
                'is_active'   => $request->boolean('is_active', true),
            ]);
            DB::commit();
            return redirect()->route('master.units.index')->with('success', 'Satuan berhasil ditambahkan.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withInput()->with('error', 'Gagal menyimpan satuan. Silakan coba lagi.');
        }
    }

    public function show($id) { return redirect()->route('master.units.index'); }

    public function edit($id)
    {
        $data = Unit::withCount('items')->findOrFail($id);
        return view('master.units.form', ['typeForm' => 'edit', 'data' => $data]);
    }

    public function update(Request $request, $id)
    {
        $unit = Unit::findOrFail($id);
        $request->validate([
            'code'        => 'required|string|max:20|unique:units,code,' . $id,
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'is_active'   => 'boolean',
        ]);
        DB::beginTransaction();
        try {
            $unit->update([
                'code'        => strtoupper($request->code),
                'name'        => $request->name,
                'description' => $request->description,
                'is_active'   => $request->boolean('is_active', true),
            ]);
            DB::commit();
            return redirect()->route('master.units.index')->with('success', 'Satuan berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withInput()->with('error', 'Gagal memperbarui satuan. Silakan coba lagi.');
        }
    }

    public function destroy($id)
    {
        $unit = Unit::withCount('items')->findOrFail($id);
        if ($unit->items_count > 0) {
            return response()->json(['status' => 'error', 'message' => 'Satuan tidak bisa dihapus karena masih digunakan oleh ' . $unit->items_count . ' sparepart.'], 422);
        }
        DB::beginTransaction();
        try {
            $unit->delete();
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Satuan berhasil dihapus.']);
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return response()->json(['status' => 'error', 'message' => 'Terjadi kesalahan pada server.'], 500);
        }
    }

    public function datatable(Request $request)
    {
        $query = Unit::withCount('items');
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
                $editUrl = route('master.units.edit', $row->id);
                $html  = '<a href="' . $editUrl . '" class="btn btn-xs btn-warning" title="Edit"><i class="fas fa-edit"></i></a> ';
                $html .= '<button class="btn btn-xs btn-danger btnDel" data-id="' . $row->id . '" data-name="' . $row->name . '" title="Hapus"><i class="fas fa-trash"></i></button>';
                return $html;
            })
            ->rawColumns(['status_badge', 'action'])
            ->make(true);
    }
}
