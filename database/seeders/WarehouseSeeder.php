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
    // Level labels A-G (7 levels)
    const LEVELS = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];

    public function run()
    {
        // Ambil kategori yang sudah di-seed sebelumnya
        $cat = ItemCategory::pluck('id', 'code');

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
        // 3 zona: Fast Moving (rak 1–8), Slow Moving (rak 9–16), Heavy (rak 17–20)
        //
        // Distribusi kategori per rak (FC_CAT GA reference):
        //   Zona A – Fast Moving  : Consumables(×2), Brake(×2), Electrical(×2), Fuel(×1), Accessories(×1)
        //   Zona B – Slow Moving  : Engine(×2), Cooling(×2), Transmission(×1), Suspension(×1), Body&Frame(×1), Fuel(×1)
        //   Zona C – Heavy        : Body&Frame(×1), Suspension(×1), Transmission(×1), Engine(×1)
        $zoneData = [
            [
                'code'  => 'A',
                'name'  => 'Zona A – Fast Moving',
                'pos_x' => 0,
                'pos_z' => 0,
                'racks' => [
                    ['no' => 1,  'cat' => 'CAT-10'], // Consumables
                    ['no' => 2,  'cat' => 'CAT-10'], // Consumables
                    ['no' => 3,  'cat' => 'CAT-05'], // Brake System
                    ['no' => 4,  'cat' => 'CAT-05'], // Brake System
                    ['no' => 5,  'cat' => 'CAT-02'], // Electrical Components
                    ['no' => 6,  'cat' => 'CAT-02'], // Electrical Components
                    ['no' => 7,  'cat' => 'CAT-07'], // Fuel System
                    ['no' => 8,  'cat' => 'CAT-09'], // Accessories
                ],
            ],
            [
                'code'  => 'B',
                'name'  => 'Zona B – Slow Moving',
                'pos_x' => 0,
                'pos_z' => 8,
                'racks' => [
                    ['no' => 9,  'cat' => 'CAT-01'], // Engine Parts
                    ['no' => 10, 'cat' => 'CAT-01'], // Engine Parts
                    ['no' => 11, 'cat' => 'CAT-06'], // Cooling System
                    ['no' => 12, 'cat' => 'CAT-06'], // Cooling System
                    ['no' => 13, 'cat' => 'CAT-04'], // Transmission
                    ['no' => 14, 'cat' => 'CAT-08'], // Suspension & Steering
                    ['no' => 15, 'cat' => 'CAT-03'], // Body & Frame
                    ['no' => 16, 'cat' => 'CAT-07'], // Fuel System
                ],
            ],
            [
                'code'  => 'C',
                'name'  => 'Zona C – Heavy',
                'pos_x' => 0,
                'pos_z' => 16,
                'racks' => [
                    ['no' => 17, 'cat' => 'CAT-03'], // Body & Frame
                    ['no' => 18, 'cat' => 'CAT-08'], // Suspension & Steering
                    ['no' => 19, 'cat' => 'CAT-04'], // Transmission
                    ['no' => 20, 'cat' => 'CAT-01'], // Engine Parts
                ],
            ],
        ];

        foreach ($zoneData as $zd) {
            $zone = Zone::create([
                'warehouse_id' => $warehouse->id,
                'code'         => $zd['code'],
                'name'         => $zd['name'],
                'pos_x'        => $zd['pos_x'],
                'pos_z'        => $zd['pos_z'],
                'is_active'    => true,
            ]);

            // Each zone has 2 rows: front (pos_z=0) and back (pos_z=2.4)
            $racksPerRow = intdiv(count($zd['racks']), 2);

            foreach ($zd['racks'] as $i => $rackInfo) {
                $rackNo = $rackInfo['no'];
                $catId  = $cat[$rackInfo['cat']] ?? null;

                $rack = Rack::create([
                    'zone_id'              => $zone->id,
                    'warehouse_id'         => $warehouse->id,
                    'dominant_category_id' => $catId,
                    'code'                 => (string) $rackNo,
                    'name'                 => 'Rak ' . $rackNo,
                    'total_levels'         => 7,
                    'total_columns'        => 1,
                    'pos_x'                => ($i % $racksPerRow) * 4,
                    'pos_z'                => floor($i / $racksPerRow) * 2.4,
                    'rotation_y'           => 0,
                    'is_active'            => true,
                ]);

                // Generate 7 sel per rak: {rackCode}-A sampai {rackCode}-G
                foreach (self::LEVELS as $idx => $letter) {
                    $cellCode = $rackNo . '-' . $letter;
                    Cell::create([
                        'rack_id'              => $rack->id,
                        'dominant_category_id' => $catId,   // sel ikut kategori raknya
                        'zone_category'        => $zd['code'], // 'A', 'B', atau 'C' — dipakai FC_CAT GA
                        'code'                 => $cellCode,
                        'qr_code'              => $cellCode,  // QR code = cell code (scannable)
                        'level'                => $idx + 1,   // 1=A, 2=B, ..., 7=G
                        'column'               => 1,
                        'capacity_max'         => 50,
                        'capacity_used'        => 0,
                        'status'               => 'available',
                        'is_active'            => true,
                    ]);
                }
            }
        }
    }
}
