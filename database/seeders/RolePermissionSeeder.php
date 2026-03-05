<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        // ── 1. Buat Roles ──────────────────────────────────────────
        $admin = Role::create([
            'name'        => 'Admin',
            'slug'        => 'admin',
            'description' => 'Akses penuh ke seluruh sistem WMS',
        ]);

        $supervisor = Role::create([
            'name'        => 'Supervisor',
            'slug'        => 'supervisor',
            'description' => 'Dapat melihat, menyetujui, dan override rekomendasi GA',
        ]);

        $operator = Role::create([
            'name'        => 'Operator',
            'slug'        => 'operator',
            'description' => 'Operator gudang: inbound, put-away, scan barcode',
        ]);

        // ── 2. Definisi Permissions per Modul ─────────────────────
        $modules = [
            'dashboard'  => ['view'],
            'item'       => ['view', 'insert', 'update', 'delete'],
            'category'   => ['view', 'insert', 'update', 'delete'],
            'unit'       => ['view', 'insert', 'update', 'delete'],
            'supplier'   => ['view', 'insert', 'update', 'delete'],
            'warehouse'  => ['view', 'insert', 'update', 'delete'],
            'zone'       => ['view', 'insert', 'update', 'delete'],
            'rack'       => ['view', 'insert', 'update', 'delete'],
            'cell'       => ['view', 'insert', 'update', 'delete'],
            'inbound'    => ['view', 'insert', 'update', 'delete'],
            'putaway'    => ['view', 'insert', 'update', 'override'],
            'stock'      => ['view', 'insert', 'update', 'delete'],
            'movement'   => ['view', 'insert'],
            'opname'     => ['view', 'insert', 'update'],
            'report'     => ['view', 'export'],
            'user'       => ['view', 'insert', 'update', 'delete'],
            'role'       => ['view', 'insert', 'update', 'delete'],
        ];

        $allPermissions = [];
        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                $perm = Permission::create([
                    'name'   => ucfirst($module) . ' - ' . ucfirst($action),
                    'slug'   => $module . '.' . $action,
                    'module' => $module,
                    'action' => $action,
                ]);
                $allPermissions[] = $perm->id;
            }
        }

        // ── 3. Assign Permissions ke Role ─────────────────────────

        // Admin: semua permission
        $admin->permissions()->sync($allPermissions);

        // Supervisor: semua kecuali user & role management
        $supervisorPerms = Permission::whereNotIn('module', ['user', 'role'])->pluck('id');
        $supervisor->permissions()->sync($supervisorPerms);

        // Operator: view & operasional terbatas
        $operatorModules = ['dashboard', 'item', 'inbound', 'putaway', 'stock', 'movement'];
        $operatorPerms = Permission::whereIn('module', $operatorModules)
            ->whereIn('action', ['view', 'insert', 'update'])
            ->pluck('id');
        $operator->permissions()->sync($operatorPerms);
    }
}
