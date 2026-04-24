<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Yajra\DataTables\DataTables;

class UserController extends Controller
{
    public function index()
    {
        $roles = Role::orderBy('name')->get();
        return view('user_management.users.index', compact('roles'));
    }

    public function create()
    {
        $roles = Role::orderBy('name')->get();
        return view('user_management.users.form', [
            'typeForm' => 'create',
            'data'     => null,
            'roles'    => $roles,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'email'       => 'required|email|max:255|unique:users,email',
            'employee_id' => 'nullable|string|max:50|unique:users,employee_id',
            'role_id'     => 'required|exists:roles,id',
            'password'    => 'required|string|min:8|confirmed',
            'is_active'   => 'boolean',
        ], [
            'password.min'       => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        DB::beginTransaction();
        try {
            User::create([
                'name'        => $request->name,
                'email'       => $request->email,
                'employee_id' => $request->employee_id,
                'role_id'     => $request->role_id,
                'password'    => Hash::make($request->password),
                'is_active'   => $request->boolean('is_active', true),
            ]);
            DB::commit();
            return redirect()->route('users.index')->with('success', 'Pengguna ' . $request->name . ' berhasil ditambahkan.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withInput()->with('error', 'Gagal menyimpan pengguna. Silakan coba lagi.');
        }
    }

    public function show($id)
    {
        return redirect()->route('users.index');
    }

    public function edit($id)
    {
        $data  = User::findOrFail($id);
        $roles = Role::orderBy('name')->get();
        return view('user_management.users.form', [
            'typeForm' => 'edit',
            'data'     => $data,
            'roles'    => $roles,
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name'        => 'required|string|max:100',
            'email'       => 'required|email|max:255|unique:users,email,' . $id,
            'employee_id' => 'nullable|string|max:50|unique:users,employee_id,' . $id,
            'role_id'     => 'required|exists:roles,id',
            'password'    => 'nullable|string|min:8|confirmed',
            'is_active'   => 'boolean',
        ], [
            'password.min'       => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        // Protect last admin
        $currentRole = Role::find($user->role_id);
        $newRole     = Role::find($request->role_id);
        if ($currentRole?->slug === 'admin' && $newRole?->slug !== 'admin') {
            $adminCount = User::whereHas('role', fn($q) => $q->where('slug', 'admin'))->where('is_active', true)->count();
            if ($adminCount <= 1) {
                return back()->withInput()->with('error', 'Tidak dapat mengubah role satu-satunya admin aktif.');
            }
        }

        // Protect self deactivation
        if ($id == auth()->id() && !$request->boolean('is_active', true)) {
            return back()->withInput()->with('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
        }

        DB::beginTransaction();
        try {
            $updateData = [
                'name'        => $request->name,
                'email'       => $request->email,
                'employee_id' => $request->employee_id,
                'role_id'     => $request->role_id,
                'is_active'   => $request->boolean('is_active', true),
            ];
            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }
            $user->update($updateData);
            DB::commit();
            return redirect()->route('users.index')->with('success', 'Pengguna berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withInput()->with('error', 'Gagal memperbarui pengguna. Silakan coba lagi.');
        }
    }

    public function destroy($id)
    {
        if ($id == auth()->id()) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak dapat menghapus akun sendiri.'], 422);
        }
        $user = User::findOrFail($id);
        if ($user->role?->slug === 'admin') {
            $adminCount = User::whereHas('role', fn($q) => $q->where('slug', 'admin'))->count();
            if ($adminCount <= 1) {
                return response()->json(['status' => 'error', 'message' => 'Tidak dapat menghapus satu-satunya admin.'], 422);
            }
        }
        DB::beginTransaction();
        try {
            $user->delete();
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Pengguna berhasil dihapus.']);
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return response()->json(['status' => 'error', 'message' => 'Terjadi kesalahan pada server.'], 500);
        }
    }

    public function datatable(Request $request)
    {
        $query = User::with('role');
        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status);
        }
        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('role_badge', function ($row) {
                if (!$row->role) return '<span class="text-muted">-</span>';
                $colors = [
                    'admin'      => 'badge-danger',
                    'supervisor' => 'badge-warning text-dark',
                    'operator'   => 'badge-info',
                ];
                $cls = $colors[$row->role->slug] ?? 'badge-secondary';
                return '<span class="badge ' . $cls . '">' . e($row->role->name) . '</span>';
            })
            ->addColumn('status_badge', function ($row) {
                return $row->is_active
                    ? '<span class="badge badge-success">Aktif</span>'
                    : '<span class="badge badge-secondary">Nonaktif</span>';
            })
            ->addColumn('action', function ($row) {
                $editUrl = route('users.edit', $row->id);
                $isSelf  = $row->id === auth()->id();
                $html    = '<div class="d-flex" style="gap:4px">';
                $html   .= '<a href="' . $editUrl . '" class="btn btn-xs btn-warning flex-fill" title="Edit"><i class="fas fa-edit"></i></a>';
                if (!$isSelf) {
                    $html .= '<button class="btn btn-xs btn-danger flex-fill btnDel" data-id="' . $row->id . '" data-name="' . e($row->name) . '" title="Hapus"><i class="fas fa-trash"></i></button>';
                }
                $html .= '</div>';
                return $html;
            })
            ->rawColumns(['role_badge', 'status_badge', 'action'])
            ->make(true);
    }
}
