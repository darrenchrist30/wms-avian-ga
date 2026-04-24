<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class RoleController extends Controller
{
    public function index()
    {
        return view('user_management.roles.index');
    }

    public function create()
    {
        $permissions = Permission::orderBy('module')->orderBy('name')->get()->groupBy('module');
        return view('user_management.roles.form', [
            'typeForm'    => 'create',
            'data'        => null,
            'permissions' => $permissions,
            'rolePerms'   => [],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'slug'        => 'required|string|max:50|unique:roles,slug|regex:/^[a-z0-9_]+$/',
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ], [
            'slug.regex' => 'Slug hanya boleh huruf kecil, angka, dan underscore.',
        ]);

        DB::beginTransaction();
        try {
            $role = Role::create([
                'name'        => $request->name,
                'slug'        => $request->slug,
                'description' => $request->description,
            ]);
            if ($request->filled('permissions')) {
                $role->permissions()->sync($request->permissions);
            }
            DB::commit();
            return redirect()->route('roles.index')->with('success', 'Role ' . $request->name . ' berhasil ditambahkan.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withInput()->with('error', 'Gagal menyimpan role. Silakan coba lagi.');
        }
    }

    public function show($id)
    {
        return redirect()->route('roles.index');
    }

    public function edit($id)
    {
        $data        = Role::with('permissions')->findOrFail($id);
        $permissions = Permission::orderBy('module')->orderBy('name')->get()->groupBy('module');
        $rolePerms   = $data->permissions->pluck('id')->toArray();
        return view('user_management.roles.form', [
            'typeForm'    => 'edit',
            'data'        => $data,
            'permissions' => $permissions,
            'rolePerms'   => $rolePerms,
        ]);
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);
        $request->validate([
            'name'          => 'required|string|max:100',
            'slug'          => 'required|string|max:50|unique:roles,slug,' . $id . '|regex:/^[a-z0-9_]+$/',
            'description'   => 'nullable|string|max:255',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ], [
            'slug.regex' => 'Slug hanya boleh huruf kecil, angka, dan underscore.',
        ]);

        // Protect admin slug from being changed
        if ($role->slug === 'admin' && $request->slug !== 'admin') {
            return back()->withInput()->with('error', 'Slug role admin tidak dapat diubah.');
        }

        DB::beginTransaction();
        try {
            $role->update([
                'name'        => $request->name,
                'slug'        => $request->slug,
                'description' => $request->description,
            ]);
            $role->permissions()->sync($request->permissions ?? []);
            DB::commit();
            return redirect()->route('roles.index')->with('success', 'Role berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withInput()->with('error', 'Gagal memperbarui role. Silakan coba lagi.');
        }
    }

    public function destroy($id)
    {
        $role = Role::withCount('users')->findOrFail($id);
        if ($role->users_count > 0) {
            return response()->json(['status' => 'error', 'message' => 'Role tidak dapat dihapus karena masih digunakan oleh ' . $role->users_count . ' pengguna.'], 422);
        }
        if ($role->slug === 'admin') {
            return response()->json(['status' => 'error', 'message' => 'Role admin tidak dapat dihapus.'], 422);
        }
        DB::beginTransaction();
        try {
            $role->permissions()->detach();
            $role->delete();
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Role berhasil dihapus.']);
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return response()->json(['status' => 'error', 'message' => 'Terjadi kesalahan pada server.'], 500);
        }
    }

    public function datatable(Request $request)
    {
        $query = Role::withCount(['users', 'permissions']);
        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('slug_badge', fn($row) => '<code>' . e($row->slug) . '</code>')
            ->addColumn('action', function ($row) {
                $editUrl   = route('roles.edit', $row->id);
                $isAdmin   = $row->slug === 'admin';
                $html      = '<a href="' . $editUrl . '" class="btn btn-xs btn-warning" title="Edit"><i class="fas fa-edit"></i></a>';
                if (!$isAdmin) {
                    $html .= ' <button class="btn btn-xs btn-danger btnDel" data-id="' . $row->id . '" data-name="' . e($row->name) . '" title="Hapus"><i class="fas fa-trash"></i></button>';
                }
                return $html;
            })
            ->rawColumns(['slug_badge', 'action'])
            ->make(true);
    }
}
