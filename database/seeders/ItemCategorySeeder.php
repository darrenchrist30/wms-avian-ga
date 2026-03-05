<?php

namespace Database\Seeders;

use App\Models\ItemCategory;
use Illuminate\Database\Seeder;

class ItemCategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            ['code' => 'CAT-01', 'name' => 'Engine Parts',          'color_code' => '#ef4444', 'description' => 'Komponen mesin'],
            ['code' => 'CAT-02', 'name' => 'Electrical Components', 'color_code' => '#f59e0b', 'description' => 'Komponen kelistrikan'],
            ['code' => 'CAT-03', 'name' => 'Body & Frame',          'color_code' => '#3b82f6', 'description' => 'Bodi dan rangka'],
            ['code' => 'CAT-04', 'name' => 'Transmission',          'color_code' => '#8b5cf6', 'description' => 'Komponen transmisi'],
            ['code' => 'CAT-05', 'name' => 'Brake System',          'color_code' => '#ec4899', 'description' => 'Sistem rem'],
            ['code' => 'CAT-06', 'name' => 'Cooling System',        'color_code' => '#14b8a6', 'description' => 'Sistem pendingin'],
            ['code' => 'CAT-07', 'name' => 'Fuel System',           'color_code' => '#f97316', 'description' => 'Sistem bahan bakar'],
            ['code' => 'CAT-08', 'name' => 'Suspension & Steering', 'color_code' => '#0d8564', 'description' => 'Suspensi dan kemudi'],
            ['code' => 'CAT-09', 'name' => 'Accessories',           'color_code' => '#64748b', 'description' => 'Aksesori kendaraan'],
            ['code' => 'CAT-10', 'name' => 'Consumables',           'color_code' => '#a16207', 'description' => 'Barang habis pakai'],
        ];

        foreach ($categories as $cat) {
            ItemCategory::create(array_merge($cat, ['is_active' => true]));
        }
    }
}
