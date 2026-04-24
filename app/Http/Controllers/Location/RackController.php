<?php

namespace App\Http\Controllers\Location;

use App\Http\Controllers\Controller;
use App\Models\Cell;
use App\Models\ItemCategory;
use App\Models\Rack;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class RackController extends Controller
{
    public function index()
    {
        $zones = Zone::with('warehouse')->where('is_active', true)->orderBy('code')->get();
        return view('location.racks.index', compact('zones'));
    }

    public function create()
    {
        $zones      = Zone::with('warehouse')->where('is_active', true)->orderBy('code')->get();
        $categories = ItemCategory::where('is_active', true)->orderBy('name')->get();
        return view('location.racks.form', ['typeForm' => 'create', 'data' => null, 'zones' => $zones, 'categories' => $categories]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'zone_id'               => 'required|exists:zones,id',
            'dominant_category_id'  => 'nullable|exists:item_categories,id',
            'code'                  => 'required|string|max:20',
            'name'                  => 'nullable|string|max:100',
            'total_levels'          => 'required|integer|min:1|max:26',
            'pos_x'                 => 'nullable|numeric',
            'pos_z'                 => 'nullable|numeric',
            'rotation_y'            => 'nullable|numeric',
            'is_active'             => 'boolean',
        ]);
        $exists = Rack::where('zone_id', $request->zone_id)
            ->where('code', strtoupper($request->code))->exists();
        if ($exists) {
            return back()->withInput()->withErrors(['code' => 'Kode rak sudah digunakan di zona ini.']);
        }
        DB::beginTransaction();
        try {
            $rack = Rack::create([
                'zone_id'              => $request->zone_id,
                'dominant_category_id' => $request->dominant_category_id,
                'code'                 => strtoupper($request->code),
                'name'                 => $request->name,
                'total_levels'         => $request->total_levels,
                'total_columns'        => 1,
                'pos_x'                => $request->pos_x ?? 0,
                'pos_z'                => $request->pos_z ?? 0,
                'rotation_y'           => $request->rotation_y ?? 0,
                'is_active'            => $request->boolean('is_active', true),
            ]);
            // Generate sel: {kode_rak}-A sampai {kode_rak}-{huruf ke-N}
            for ($level = 1; $level <= $rack->total_levels; $level++) {
                $letter = chr(64 + $level); // 1=A, 2=B, ..., 7=G
                Cell::create([
                    'rack_id'       => $rack->id,
                    'code'          => $rack->code . '-' . $letter,
                    'level'         => $level,
                    'column'        => 1,
                    'capacity_max'  => 100,
                    'capacity_used' => 0,
                    'status'        => 'available',
                    'is_active'     => true,
                ]);
            }
            DB::commit();
            return redirect()->route('location.racks.index')
                ->with('success', 'Rak ' . strtoupper($request->code) . ' berhasil ditambahkan beserta ' . $request->total_levels . ' sel (A–' . chr(64 + $request->total_levels) . ').');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withInput()->with('error', 'Gagal menyimpan rak. Silakan coba lagi.');
        }
    }

    public function show($id) { return redirect()->route('location.racks.index'); }

    public function edit($id)
    {
        $data       = Rack::withCount('cells')->findOrFail($id);
        $zones      = Zone::with('warehouse')->where('is_active', true)->orderBy('code')->get();
        $categories = ItemCategory::where('is_active', true)->orderBy('name')->get();
        return view('location.racks.form', ['typeForm' => 'edit', 'data' => $data, 'zones' => $zones, 'categories' => $categories]);
    }

    public function update(Request $request, $id)
    {
        $rack = Rack::findOrFail($id);
        $request->validate([
            'zone_id'              => 'required|exists:zones,id',
            'dominant_category_id' => 'nullable|exists:item_categories,id',
            'code'                 => 'required|string|max:20',
            'name'                 => 'nullable|string|max:100',
            'pos_x'                => 'nullable|numeric',
            'pos_z'                => 'nullable|numeric',
            'rotation_y'           => 'nullable|numeric',
            'is_active'            => 'boolean',
        ]);
        $exists = Rack::where('zone_id', $request->zone_id)
            ->where('code', strtoupper($request->code))
            ->where('id', '!=', $id)->exists();
        if ($exists) {
            return back()->withInput()->withErrors(['code' => 'Kode rak sudah digunakan di zona ini.']);
        }
        DB::beginTransaction();
        try {
            $rack->update([
                'zone_id'              => $request->zone_id,
                'dominant_category_id' => $request->dominant_category_id,
                'code'                 => strtoupper($request->code),
                'name'                 => $request->name,
                'pos_x'                => $request->pos_x ?? $rack->pos_x,
                'pos_z'                => $request->pos_z ?? $rack->pos_z,
                'rotation_y'           => $request->rotation_y ?? $rack->rotation_y,
                'is_active'            => $request->boolean('is_active', true),
            ]);
            DB::commit();
            return redirect()->route('location.racks.index')->with('success', 'Rak berhasil diperbarui.');
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

    public function datatable(Request $request)
    {
        $query = Rack::with(['zone.warehouse', 'dominantCategory'])->withCount('cells');
        if ($request->filled('zone_id')) {
            $query->where('zone_id', $request->zone_id);
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status);
        }
        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('lokasi', function ($row) {
                $wh   = $row->zone->warehouse->name ?? '-';
                $zone = $row->zone->code ?? '-';
                return '<small class="text-muted">' . e($wh) . ' / <span class="text-primary">' . e($zone) . '</span></small>';
            })
            ->addColumn('kategori', function ($row) {
                if (!$row->dominantCategory) return '<span class="text-muted">-</span>';
                $color = $row->dominantCategory->color_code ?? '#6c757d';
                $name  = e($row->dominantCategory->name);
                return '<span class="badge" style="background:' . $color . ';color:#fff;">' . $name . '</span>';
            })
            ->addColumn('status_badge', function ($row) {
                return $row->is_active
                    ? '<span class="badge badge-success">Aktif</span>'
                    : '<span class="badge badge-secondary">Nonaktif</span>';
            })
            ->addColumn('action', function ($row) {
                $editUrl = route('location.racks.edit', $row->id);
                $html  = '<a href="' . $editUrl . '" class="btn btn-xs btn-warning" title="Edit"><i class="fas fa-edit"></i></a> ';
                $html .= '<button class="btn btn-xs btn-danger btnDel" data-id="' . $row->id . '" data-name="' . e($row->code) . '" title="Hapus"><i class="fas fa-trash"></i></button>';
                return $html;
            })
            ->rawColumns(['lokasi', 'kategori', 'status_badge', 'action'])
            ->make(true);
    }
}
