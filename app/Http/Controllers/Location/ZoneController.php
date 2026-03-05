<?php

namespace App\Http\Controllers\Location;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ZoneController extends Controller
{
    public function index()
    {
        $zones = Zone::with('warehouse')->withCount('racks')->latest()->get();
        return view('location.zones.index', compact('zones'));
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
            ->where('code', strtoupper($request->code))
            ->exists();

        if ($exists) {
            return back()->withInput()
                ->withErrors(['code' => 'Kode zona sudah digunakan di warehouse ini.']);
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

            return redirect()->route('location.zones.index')
                ->with('success', 'Zona berhasil ditambahkan.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                ->with('error', 'Gagal menyimpan zona: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        return redirect()->route('location.zones.index');
    }

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
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            return back()->withInput()
                ->withErrors(['code' => 'Kode zona sudah digunakan di warehouse ini.']);
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

            return redirect()->route('location.zones.index')
                ->with('success', 'Zona berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                ->with('error', 'Gagal memperbarui zona: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $data = Zone::withCount('racks')->findOrFail($id);

        if ($data->racks_count > 0) {
            return back()->with('error', 'Zona tidak dapat dihapus karena masih memiliki ' . $data->racks_count . ' rak.');
        }

        DB::beginTransaction();

        try {
            $data->delete();

            DB::commit();

            return back()->with('success', 'Zona berhasil dihapus.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menghapus zona: ' . $e->getMessage());
        }
    }
}
