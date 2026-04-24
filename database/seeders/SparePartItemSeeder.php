<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SparePartItemSeeder extends Seeder
{
    public function run(): void
    {
        // Hapus item lama (cat, plamir, dll yang tidak relevan)
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('items')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $cat  = ItemCategory::pluck('id', 'name');
        $unit = Unit::pluck('id', 'code');

        // ─── Kategori ID ───────────────────────────────────────────
        $ENGINE   = $cat['Engine Parts']           ?? 1;
        $ELEC     = $cat['Electrical Components']  ?? 2;
        $FRAME    = $cat['Body & Frame']            ?? 3;
        $TRANS    = $cat['Transmission']            ?? 4;
        $BRAKE    = $cat['Brake System']            ?? 5;
        $COOL     = $cat['Cooling System']          ?? 6;
        $FUEL     = $cat['Fuel System']             ?? 7;
        $SUSP     = $cat['Suspension & Steering']   ?? 8;
        $ACC      = $cat['Accessories']             ?? 9;
        $CONS     = $cat['Consumables']             ?? 10;

        // ─── Unit ID ───────────────────────────────────────────────
        $PCS  = $unit['PCS']  ?? 1;
        $BOX  = $unit['BOX']  ?? 2;
        $SET  = $unit['SET']  ?? 3;
        $PAR  = $unit['PAR']  ?? 4;
        $KG   = $unit['KG']   ?? 5;
        $LTR  = $unit['LTR']  ?? 6;
        $MTR  = $unit['MTR']  ?? 7;
        $ROLL = $unit['ROLL'] ?? 8;

        $items = [
            // ── BEARING ──────────────────────────────────────────────
            [
                'category_id'  => $ENGINE,
                'unit_id'      => $PCS,
                'sku'          => 'SP-BRG-001',
                'erp_item_code'=> 'ERP-BRG-001',
                'barcode'      => 'SP-BRG-001',
                'name'         => 'Bearing SKF 6205-2RS',
                'item_size'    => 'small',
                'description'  => 'Deep groove ball bearing, ID 25mm OD 52mm, sealed',
                'min_stock'    => 10,
                'max_stock'    => 100,
                'reorder_point'=> 20,
                'movement_type'=> 'fast_moving',
                'weight_kg'    => 0.15,
            ],
            [
                'category_id'  => $ENGINE,
                'unit_id'      => $PCS,
                'sku'          => 'SP-BRG-002',
                'erp_item_code'=> 'ERP-BRG-002',
                'barcode'      => 'SP-BRG-002',
                'name'         => 'Bearing SKF 6306-2Z',
                'item_size'    => 'small',
                'description'  => 'Deep groove ball bearing, ID 30mm OD 72mm, shielded',
                'min_stock'    => 8,
                'max_stock'    => 80,
                'reorder_point'=> 15,
                'movement_type'=> 'fast_moving',
                'weight_kg'    => 0.25,
            ],
            [
                'category_id'  => $ENGINE,
                'unit_id'      => $PCS,
                'sku'          => 'SP-BRG-003',
                'erp_item_code'=> 'ERP-BRG-003',
                'barcode'      => 'SP-BRG-003',
                'name'         => 'Bearing FAG 6208-C3',
                'item_size'    => 'small',
                'description'  => 'Single row ball bearing, clearance C3, ID 40mm OD 80mm',
                'min_stock'    => 5,
                'max_stock'    => 50,
                'reorder_point'=> 10,
                'movement_type'=> 'slow_moving',
                'weight_kg'    => 0.32,
            ],
            [
                'category_id'  => $ENGINE,
                'unit_id'      => $PCS,
                'sku'          => 'SP-BRG-004',
                'erp_item_code'=> 'ERP-BRG-004',
                'barcode'      => 'SP-BRG-004',
                'name'         => 'Pillow Block Bearing UCP-205',
                'item_size'    => 'medium',
                'description'  => 'Cast iron pillow block unit bearing, bore 25mm',
                'min_stock'    => 4,
                'max_stock'    => 30,
                'reorder_point'=> 8,
                'movement_type'=> 'slow_moving',
                'weight_kg'    => 0.85,
            ],

            // ── SEAL & GASKET ─────────────────────────────────────────
            [
                'category_id'  => $ENGINE,
                'unit_id'      => $PCS,
                'sku'          => 'SP-SEAL-001',
                'erp_item_code'=> 'ERP-SEAL-001',
                'barcode'      => 'SP-SEAL-001',
                'name'         => 'Oil Seal TC 35×52×7',
                'item_size'    => 'small',
                'description'  => 'Rotary shaft seal, NBR material, 35mm shaft',
                'min_stock'    => 10,
                'max_stock'    => 100,
                'reorder_point'=> 20,
                'movement_type'=> 'fast_moving',
                'weight_kg'    => 0.05,
            ],
            [
                'category_id'  => $ENGINE,
                'unit_id'      => $SET,
                'sku'          => 'SP-GSK-001',
                'erp_item_code'=> 'ERP-GSK-001',
                'barcode'      => 'SP-GSK-001',
                'name'         => 'Gasket Set Pompa Sentrifugal',
                'item_size'    => 'small',
                'description'  => 'Set gasket untuk pompa sentrifugal 3 inch, NBR',
                'min_stock'    => 5,
                'max_stock'    => 40,
                'reorder_point'=> 10,
                'movement_type'=> 'slow_moving',
                'weight_kg'    => 0.12,
            ],

            // ── V-BELT & CHAIN ────────────────────────────────────────
            [
                'category_id'  => $TRANS,
                'unit_id'      => $PCS,
                'sku'          => 'SP-BELT-001',
                'erp_item_code'=> 'ERP-BELT-001',
                'barcode'      => 'SP-BELT-001',
                'name'         => 'V-Belt A-42 (Gates)',
                'item_size'    => 'medium',
                'description'  => 'Classical V-belt section A, length 42 inch',
                'min_stock'    => 10,
                'max_stock'    => 80,
                'reorder_point'=> 20,
                'movement_type'=> 'fast_moving',
                'weight_kg'    => 0.18,
            ],
            [
                'category_id'  => $TRANS,
                'unit_id'      => $PCS,
                'sku'          => 'SP-BELT-002',
                'erp_item_code'=> 'ERP-BELT-002',
                'barcode'      => 'SP-BELT-002',
                'name'         => 'V-Belt B-60 (Gates)',
                'item_size'    => 'medium',
                'description'  => 'Classical V-belt section B, length 60 inch',
                'min_stock'    => 8,
                'max_stock'    => 60,
                'reorder_point'=> 15,
                'movement_type'=> 'fast_moving',
                'weight_kg'    => 0.28,
            ],
            [
                'category_id'  => $TRANS,
                'unit_id'      => $MTR,
                'sku'          => 'SP-CHAIN-001',
                'erp_item_code'=> 'ERP-CHAIN-001',
                'barcode'      => 'SP-CHAIN-001',
                'name'         => 'Roller Chain #50 (per meter)',
                'item_size'    => 'medium',
                'description'  => 'Standard roller chain pitch 15.875mm, simplex',
                'min_stock'    => 5,
                'max_stock'    => 50,
                'reorder_point'=> 10,
                'movement_type'=> 'slow_moving',
                'weight_kg'    => 0.95,
            ],

            // ── FASTENER (BAUT & MUR) ─────────────────────────────────
            [
                'category_id'  => $FRAME,
                'unit_id'      => $BOX,
                'sku'          => 'SP-BOLT-001',
                'erp_item_code'=> 'ERP-BOLT-001',
                'barcode'      => 'SP-BOLT-001',
                'name'         => 'Baut Hex M10×40 Grade 8.8 (box 100pcs)',
                'item_size'    => 'small',
                'description'  => 'Hexagon bolt M10 L=40mm, Grade 8.8, zinc plated',
                'min_stock'    => 5,
                'max_stock'    => 50,
                'reorder_point'=> 10,
                'movement_type'=> 'fast_moving',
                'weight_kg'    => 0.68,
            ],
            [
                'category_id'  => $FRAME,
                'unit_id'      => $BOX,
                'sku'          => 'SP-BOLT-002',
                'erp_item_code'=> 'ERP-BOLT-002',
                'barcode'      => 'SP-BOLT-002',
                'name'         => 'Mur Hex M10 Grade 8 (box 200pcs)',
                'item_size'    => 'small',
                'description'  => 'Hexagon nut M10, Grade 8, zinc plated',
                'min_stock'    => 5,
                'max_stock'    => 50,
                'reorder_point'=> 10,
                'movement_type'=> 'fast_moving',
                'weight_kg'    => 0.55,
            ],
            [
                'category_id'  => $FRAME,
                'unit_id'      => $BOX,
                'sku'          => 'SP-BOLT-003',
                'erp_item_code'=> 'ERP-BOLT-003',
                'barcode'      => 'SP-BOLT-003',
                'name'         => 'Baut Hex M12×50 Grade 8.8 (box 50pcs)',
                'item_size'    => 'small',
                'description'  => 'Hexagon bolt M12 L=50mm, Grade 8.8, zinc plated',
                'min_stock'    => 5,
                'max_stock'    => 40,
                'reorder_point'=> 10,
                'movement_type'=> 'fast_moving',
                'weight_kg'    => 0.72,
            ],

            // ── FILTER ────────────────────────────────────────────────
            [
                'category_id'  => $COOL,
                'unit_id'      => $PCS,
                'sku'          => 'SP-FILT-001',
                'erp_item_code'=> 'ERP-FILT-001',
                'barcode'      => 'SP-FILT-001',
                'name'         => 'Filter Oli Mesin W920/21',
                'item_size'    => 'small',
                'description'  => 'Oil filter cartridge, spin-on type, kompatibel mesin produksi',
                'min_stock'    => 10,
                'max_stock'    => 80,
                'reorder_point'=> 20,
                'movement_type'=> 'fast_moving',
                'weight_kg'    => 0.22,
            ],
            [
                'category_id'  => $COOL,
                'unit_id'      => $PCS,
                'sku'          => 'SP-FILT-002',
                'erp_item_code'=> 'ERP-FILT-002',
                'barcode'      => 'SP-FILT-002',
                'name'         => 'Filter Udara Kompresor AF25708',
                'item_size'    => 'medium',
                'description'  => 'Air filter element untuk kompresor industri',
                'min_stock'    => 6,
                'max_stock'    => 40,
                'reorder_point'=> 12,
                'movement_type'=> 'slow_moving',
                'weight_kg'    => 0.45,
            ],
            [
                'category_id'  => $FUEL,
                'unit_id'      => $PCS,
                'sku'          => 'SP-FILT-003',
                'erp_item_code'=> 'ERP-FILT-003',
                'barcode'      => 'SP-FILT-003',
                'name'         => 'Filter Hydraulic HF28897',
                'item_size'    => 'medium',
                'description'  => 'Hydraulic filter element, high pressure, 10 mikron',
                'min_stock'    => 5,
                'max_stock'    => 30,
                'reorder_point'=> 10,
                'movement_type'=> 'slow_moving',
                'weight_kg'    => 0.38,
            ],

            // ── ELECTRICAL ────────────────────────────────────────────
            [
                'category_id'  => $ELEC,
                'unit_id'      => $PCS,
                'sku'          => 'SP-ELEC-001',
                'erp_item_code'=> 'ERP-ELEC-001',
                'barcode'      => 'SP-ELEC-001',
                'name'         => 'Kontaktor Schneider LC1D25 25A',
                'item_size'    => 'small',
                'description'  => 'AC contactor 25A, coil 220VAC, 3 pole',
                'min_stock'    => 4,
                'max_stock'    => 30,
                'reorder_point'=> 8,
                'movement_type'=> 'slow_moving',
                'weight_kg'    => 0.45,
            ],
            [
                'category_id'  => $ELEC,
                'unit_id'      => $PCS,
                'sku'          => 'SP-ELEC-002',
                'erp_item_code'=> 'ERP-ELEC-002',
                'barcode'      => 'SP-ELEC-002',
                'name'         => 'MCB Schneider iC60N 20A 3P',
                'item_size'    => 'small',
                'description'  => 'Miniature circuit breaker 20A, 3 pole, 6kA',
                'min_stock'    => 5,
                'max_stock'    => 40,
                'reorder_point'=> 10,
                'movement_type'=> 'slow_moving',
                'weight_kg'    => 0.28,
            ],
            [
                'category_id'  => $ELEC,
                'unit_id'      => $ROLL,
                'sku'          => 'SP-ELEC-003',
                'erp_item_code'=> 'ERP-ELEC-003',
                'barcode'      => 'SP-ELEC-003',
                'name'         => 'Kabel NYY 4×4mm² (per roll 50m)',
                'item_size'    => 'large',
                'description'  => 'Power cable NYY 4 core 4mm², 0.6/1kV',
                'min_stock'    => 3,
                'max_stock'    => 20,
                'reorder_point'=> 5,
                'movement_type'=> 'slow_moving',
                'weight_kg'    => 12.5,
            ],
            [
                'category_id'  => $ELEC,
                'unit_id'      => $PCS,
                'sku'          => 'SP-ELEC-004',
                'erp_item_code'=> 'ERP-ELEC-004',
                'barcode'      => 'SP-ELEC-004',
                'name'         => 'Relay Omron MY4N 24VDC',
                'item_size'    => 'small',
                'description'  => 'General purpose relay, 4PDT, coil 24VDC, 5A',
                'min_stock'    => 10,
                'max_stock'    => 80,
                'reorder_point'=> 20,
                'movement_type'=> 'fast_moving',
                'weight_kg'    => 0.06,
            ],

            // ── COUPLING & GEAR ───────────────────────────────────────
            [
                'category_id'  => $TRANS,
                'unit_id'      => $SET,
                'sku'          => 'SP-COUP-001',
                'erp_item_code'=> 'ERP-COUP-001',
                'barcode'      => 'SP-COUP-001',
                'name'         => 'Flexible Coupling Jaw Type L075',
                'item_size'    => 'small',
                'description'  => 'Spider jaw coupling L075, bore 14-19mm, polyurethane insert',
                'min_stock'    => 4,
                'max_stock'    => 24,
                'reorder_point'=> 8,
                'movement_type'=> 'slow_moving',
                'weight_kg'    => 0.18,
            ],
            [
                'category_id'  => $TRANS,
                'unit_id'      => $PCS,
                'sku'          => 'SP-COUP-002',
                'erp_item_code'=> 'ERP-COUP-002',
                'barcode'      => 'SP-COUP-002',
                'name'         => 'Spider Insert Coupling L075 (Polyurethane)',
                'item_size'    => 'small',
                'description'  => 'Replacement spider/insert for L075 jaw coupling, Shore 98A',
                'min_stock'    => 10,
                'max_stock'    => 60,
                'reorder_point'=> 20,
                'movement_type'=> 'fast_moving',
                'weight_kg'    => 0.04,
            ],

            // ── CONSUMABLES ───────────────────────────────────────────
            [
                'category_id'  => $CONS,
                'unit_id'      => $LTR,
                'sku'          => 'SP-OIL-001',
                'erp_item_code'=> 'ERP-OIL-001',
                'barcode'      => 'SP-OIL-001',
                'name'         => 'Oli Pelumas Mesin SAE 40 (per liter)',
                'item_size'    => 'medium',
                'description'  => 'Industrial engine oil SAE 40, mineral based',
                'min_stock'    => 20,
                'max_stock'    => 200,
                'reorder_point'=> 40,
                'movement_type'=> 'fast_moving',
                'weight_kg'    => 0.90,
            ],
            [
                'category_id'  => $CONS,
                'unit_id'      => $KG,
                'sku'          => 'SP-GRS-001',
                'erp_item_code'=> 'ERP-GRS-001',
                'barcode'      => 'SP-GRS-001',
                'name'         => 'Grease Shell Alvania R2 (per kg)',
                'item_size'    => 'small',
                'description'  => 'Lithium-based grease NLGI 2, untuk bearing dan sliding',
                'min_stock'    => 10,
                'max_stock'    => 80,
                'reorder_point'=> 20,
                'movement_type'=> 'fast_moving',
                'weight_kg'    => 1.00,
            ],
            [
                'category_id'  => $CONS,
                'unit_id'      => $PCS,
                'sku'          => 'SP-CONS-001',
                'erp_item_code'=> 'ERP-CONS-001',
                'barcode'      => 'SP-CONS-001',
                'name'         => 'Mata Gerinda Potong 4" (Cutting Disc)',
                'item_size'    => 'small',
                'description'  => 'Abrasive cutting disc 4 inch (105×1×16mm), untuk besi',
                'min_stock'    => 20,
                'max_stock'    => 200,
                'reorder_point'=> 40,
                'movement_type'=> 'fast_moving',
                'weight_kg'    => 0.08,
            ],

            // ── PNEUMATIK ─────────────────────────────────────────────
            [
                'category_id'  => $FUEL,
                'unit_id'      => $PCS,
                'sku'          => 'SP-PNM-001',
                'erp_item_code'=> 'ERP-PNM-001',
                'barcode'      => 'SP-PNM-001',
                'name'         => 'Solenoid Valve SMC SY5120-5LZD',
                'item_size'    => 'small',
                'description'  => '5/2 way solenoid valve, 24VDC, port 1/4 inch',
                'min_stock'    => 4,
                'max_stock'    => 24,
                'reorder_point'=> 8,
                'movement_type'=> 'slow_moving',
                'weight_kg'    => 0.22,
            ],
            [
                'category_id'  => $FUEL,
                'unit_id'      => $PCS,
                'sku'          => 'SP-PNM-002',
                'erp_item_code'=> 'ERP-PNM-002',
                'barcode'      => 'SP-PNM-002',
                'name'         => 'Air Cylinder SMC CQ2B40-50D',
                'item_size'    => 'medium',
                'description'  => 'Compact pneumatic cylinder, bore 40mm, stroke 50mm',
                'min_stock'    => 3,
                'max_stock'    => 15,
                'reorder_point'=> 5,
                'movement_type'=> 'slow_moving',
                'weight_kg'    => 0.55,
            ],

            // ── TOOLS & ACCESSORIES ───────────────────────────────────
            [
                'category_id'  => $ACC,
                'unit_id'      => $SET,
                'sku'          => 'SP-TOOL-001',
                'erp_item_code'=> 'ERP-TOOL-001',
                'barcode'      => 'SP-TOOL-001',
                'name'         => 'Kunci Ring Pas Set 8–19mm (9pcs)',
                'item_size'    => 'medium',
                'description'  => 'Combination wrench set CrV 8,10,11,12,13,14,17,19mm',
                'min_stock'    => 3,
                'max_stock'    => 15,
                'reorder_point'=> 5,
                'movement_type'=> 'slow_moving',
                'weight_kg'    => 1.20,
            ],
            [
                'category_id'  => $BRAKE,
                'unit_id'      => $PAR,
                'sku'          => 'SP-BRAKE-001',
                'erp_item_code'=> 'ERP-BRAKE-001',
                'barcode'      => 'SP-BRAKE-001',
                'name'         => 'Brake Pad Forklift TCM FD30',
                'item_size'    => 'medium',
                'description'  => 'Brake lining/pad untuk forklift TCM FD30, 1 set = 2 pcs',
                'min_stock'    => 4,
                'max_stock'    => 20,
                'reorder_point'=> 8,
                'movement_type'=> 'slow_moving',
                'weight_kg'    => 0.65,
            ],
            [
                'category_id'  => $SUSP,
                'unit_id'      => $PCS,
                'sku'          => 'SP-SUSP-001',
                'erp_item_code'=> 'ERP-SUSP-001',
                'barcode'      => 'SP-SUSP-001',
                'name'         => 'Solid Tyre Forklift 6.00-9 (Ban Mati)',
                'item_size'    => 'large',
                'description'  => 'Solid rubber tyre 6.00-9 untuk forklift, press-on type',
                'min_stock'    => 2,
                'max_stock'    => 12,
                'reorder_point'=> 4,
                'movement_type'=> 'slow_moving',
                'weight_kg'    => 18.0,
            ],
        ];

        foreach ($items as $data) {
            Item::create(array_merge($data, [
                'is_active'                => true,
                'deadstock_threshold_days' => 90,
                'volume_m3'                => null,
                'image'                    => null,
            ]));
        }

        $this->command->info('✅ ' . count($items) . ' sparepart items berhasil di-seed.');
    }
}
