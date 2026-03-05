<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        $admin      = Role::where('slug', 'admin')->first();
        $supervisor = Role::where('slug', 'supervisor')->first();
        $operator   = Role::where('slug', 'operator')->first();

        User::create([
            'role_id'     => $admin->id,
            'employee_id' => 'EMP-001',
            'name'        => 'Administrator',
            'email'       => 'admin@avianbrands.com',
            'password'    => Hash::make('admin123'),
            'is_active'   => true,
        ]);

        User::create([
            'role_id'     => $supervisor->id,
            'employee_id' => 'EMP-002',
            'name'        => 'Supervisor Gudang',
            'email'       => 'supervisor@avianbrands.com',
            'password'    => Hash::make('supervisor123'),
            'is_active'   => true,
        ]);

        User::create([
            'role_id'     => $operator->id,
            'employee_id' => 'EMP-003',
            'name'        => 'Operator Gudang',
            'email'       => 'operator@avianbrands.com',
            'password'    => Hash::make('operator123'),
            'is_active'   => true,
        ]);
    }
}
