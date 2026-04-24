<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\ItemCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class ItemCategoryController extends Controller
{
    public function index()
    {
        return view('master.categories.index');
    }

    public function create()
    {
        return view('master.categories.form', [
            'typeForm' => 'create',
            'data'     => null,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'code'        => 'required|string|max:20|unique:item_categories,code',
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'color_code'  => 'nullable|string|max:7',
            'is_active'   => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            ItemCategory::create([
                'code'        => strtoupper($request->code),
                'name'        => $request->name,
                'description' => $request->description,
                'color_code'  => $request->color_code ?? '#6c757d',
                'is_active'   => $request->boolean('is_active', true),
            ]);
            DB::commit();
            return redirect()->route('master.categories.index')->with('success', 'Kategori berhasil ditambahkan.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withInput()->with('error', 'Gagal menyimpan kategori. Silakan coba lagi.');
        }
    }

    public function show($id)
    {
        return redirect()->route('master.categories.index');
    }

    public function edit($id)
    {
        $data = ItemCategory::withCount('items')->findOrFail($id);
        return view('master.categories.form', ['typeForm' => 'edit', 'data' => $data]);
    }

    public function update(Request $request, $id)
    {
        $category = ItemCategory::findOrFail($id);
        $request->validate([
            'code'        => 'required|string|max:20|unique:item_categories,code,' . $id,
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'color_code'  => 'nullable|string|max:7',
            'is_active'   => 'boolean',
        ]);
        DB::beginTransaction();
        try {
            $category->update([
                'code'        => strtoupper($request->code),
                'name'        => $request->name,
                'description' => $request->description,
                'color_code'  => $request->color_code ?? $category->color_code,
                'is_active'   => $request->boolean('is_active', true),
            ]);
            DB::commit();
            return redirect()->route('master.categories.index')->with('success', 'Kategori berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withInput()->with('error', 'Gagal memperbarui kategori. Silakan coba lagi.');
        }
    }

    public function destroy($id)
    {
        $category = ItemCategory::withCount('items')->findOrFail($id);
        if ($category->items_count > 0) {
            return response()->json(['status' => 'error', 'message' => 'Kategori tidak bisa dihapus karena masih memiliki ' . $category->items_count . ' sparepart.'], 422);
        }
        DB::beginTransaction();
        try {
            $category->delete();
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Kategori berhasil dihapus.']);
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return response()->json(['status' => 'error', 'message' => 'Terjadi kesalahan pada server.'], 500);
        }
    }

    public function datatable(Request $request)
    {
        $query = ItemCategory::withCount('items');

        if ($request->filled('status')) {
            $query->where('is_active', $request->status);
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('color_badge', function ($row) {
                return '<span class="badge" style="background:' . ($row->color_code ?? '#6c757d') . ';color:#fff;padding:4px 8px;">' . $row->name . '</span>';
            })
            ->addColumn('status_badge', function ($row) {
                return $row->is_active
                    ? '<span class="badge badge-success">Aktif</span>'
                    : '<span class="badge badge-secondary">Nonaktif</span>';
            })
            ->addColumn('action', function ($row) {
                $editUrl = route('master.categories.edit', $row->id);
                $html  = '<a href="' . $editUrl . '" class="btn btn-xs btn-warning" title="Edit"><i class="fas fa-edit"></i></a> ';
                $html .= '<button class="btn btn-xs btn-danger btnDel" data-id="' . $row->id . '" data-name="' . $row->name . '" title="Hapus"><i class="fas fa-trash"></i></button>';
                return $html;
            })
            ->rawColumns(['color_badge', 'status_badge', 'action'])
            ->make(true);
    }
}
