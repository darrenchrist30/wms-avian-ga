<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use App\Models\Zone;
use App\Models\Rack;
use App\Models\Cell;
use App\Models\ItemCategory;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run()
    {
        // ── Gudang Utama ───────────────────────────────────────────
        $warehouse = Warehouse::create([
            'code'      => 'WH-001',
            'name'      => 'Gudang Sparepart PT XYZ',
            'address'   => 'Jl. Raya Industri No. 1, Surabaya, Jawa Timur',
            'pic'       => 'Supervisor Gudang',
            'phone'     => '031-12345678',
            'is_active' => true,
        ]);

        // ── Zona Gudang ────────────────────────────────────────────
        $zoneData = [
            ['code' => 'A', 'name' => 'Zona A – Fast Moving',    'pos_x' => 0,   'pos_z' => 0],
            ['code' => 'B', 'name' => 'Zona B – Slow Moving',    'pos_x' => 20,  'pos_z' => 0],
            ['code' => 'C', 'name' => 'Zona C – Staging Area',   'pos_x' => 40,  'pos_z' => 0],
            ['code' => 'D', 'name' => 'Zona D – Bulky / Heavy',  'pos_x' => 0,   'pos_z' => 20],
            ['code' => 'E', 'name' => 'Zona E – Quarantine',     'pos_x' => 20,  'pos_z' => 20],
        ];

        foreach ($zoneData as $zd) {
            $zone = Zone::create(array_merge($zd, [
                'warehouse_id' => $warehouse->id,
                'is_active'    => true,
            ]));

            // Setiap zona punya 4 rak, setiap rak punya 4 level x 3 kolom = 12 cell
            $this->createRacksForZone($zone);
        }
    }

    private function createRacksForZone(Zone $zone): void
    {
        for ($r = 1; $r <= 4; $r++) {
            $rack = Rack::create([
                'zone_id'       => $zone->id,
                'code'          => 'R-' . $zone->code . sprintf('%02d', $r),
                'name'          => 'Rak ' . $zone->code . '-' . sprintf('%02d', $r),
                'total_levels'  => 4,
                'total_columns' => 3,
                'pos_x'         => ($r - 1) * 4,
                'pos_z'         => 0,
                'rotation_y'    => 0,
                'is_active'     => true,
            ]);

            $this->createCellsForRack($rack);
        }
    }

    private function createCellsForRack(Rack $rack): void
    {
        for ($level = 1; $level <= 4; $level++) {
            for ($col = 1; $col <= 3; $col++) {
                Cell::create([
                    'rack_id'              => $rack->id,
                    'dominant_category_id' => null,
                    'code'                 => $rack->code . '-L' . $level . '-C' . $col,
                    'level'                => $level,
                    'column'               => $col,
                    'capacity_max'         => 50, // default 50 unit per cell
                    'capacity_used'        => 0,
                    'status'               => 'available',
                    'is_active'            => true,
                ]);
            }
        }
    }
}
