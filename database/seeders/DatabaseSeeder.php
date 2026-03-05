<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            RolePermissionSeeder::class, // harus pertama (user butuh role)
            UserSeeder::class,
            ItemCategorySeeder::class,
            UnitSeeder::class,
            WarehouseSeeder::class,      // warehouse → zone → rack → cell
        ]);
    }
}
