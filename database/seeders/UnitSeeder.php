<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run()
    {
        $units = [
            ['code' => 'PCS',  'name' => 'Pieces',     'description' => 'Satuan buah/pcs'],
            ['code' => 'BOX',  'name' => 'Box',        'description' => 'Satuan kotak'],
            ['code' => 'SET',  'name' => 'Set',        'description' => 'Satuan set/paket'],
            ['code' => 'PAR',  'name' => 'Pair',       'description' => 'Satuan pasang'],
            ['code' => 'KG',   'name' => 'Kilogram',   'description' => 'Satuan berat kilogram'],
            ['code' => 'LTR',  'name' => 'Liter',      'description' => 'Satuan volume liter'],
            ['code' => 'MTR',  'name' => 'Meter',      'description' => 'Satuan panjang meter'],
            ['code' => 'ROLL', 'name' => 'Roll',       'description' => 'Satuan gulungan'],
            ['code' => 'CTN',  'name' => 'Carton',     'description' => 'Satuan karton'],
            ['code' => 'PLT',  'name' => 'Pallet',     'description' => 'Satuan pallet'],
        ];

        foreach ($units as $unit) {
            Unit::create(array_merge($unit, ['is_active' => true]));
        }
    }
}
