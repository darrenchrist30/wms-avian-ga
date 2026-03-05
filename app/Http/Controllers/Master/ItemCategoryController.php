<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\ItemCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemCategoryController extends Controller
{
    public function index()
    {
        $categories = ItemCategory::withCount('items')->latest()->get();
        return view('master.categories.index', compact('categories'));
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

            return redirect()->route('master.categories.index')
                ->with('success', 'Kategori berhasil ditambahkan.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                ->with('error', 'Gagal menyimpan kategori: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        return redirect()->route('master.categories.index');
    }

    public function edit($id)
    {
        $data = ItemCategory::withCount('items')->findOrFail($id);
        return view('master.categories.form', [
            'typeForm' => 'edit',
            'data'     => $data,
        ]);
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

            return redirect()->route('master.categories.index')
                ->with('success', 'Kategori berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                ->with('error', 'Gagal memperbarui kategori: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $category = ItemCategory::withCount('items')->findOrFail($id);

        if ($category->items_count > 0) {
            return back()->with('error', 'Kategori tidak bisa dihapus karena masih memiliki ' . $category->items_count . ' sparepart.');
        }

        DB::beginTransaction();

        try {
            $category->delete();

            DB::commit();

            return redirect()->route('master.categories.index')
                ->with('success', 'Kategori berhasil dihapus.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menghapus kategori: ' . $e->getMessage());
        }
    }
}