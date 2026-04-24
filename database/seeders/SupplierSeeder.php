<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('suppliers')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $suppliers = [
            [
                'code'        => 'SUP-001',
                'name'        => 'PT Teknik Jaya Abadi',
                'contact_person' => 'Budi Santoso',
                'phone'       => '031-5678901',
                'email'       => 'purchase@teknikjaya.co.id',
                'address'     => 'Jl. Raya Industri No. 45, Surabaya',
                'is_active'   => true,
            ],
            [
                'code'        => 'SUP-002',
                'name'        => 'CV Elektrika Nusantara',
                'contact_person' => 'Siti Rahayu',
                'phone'       => '031-7654321',
                'email'       => 'sales@elektrikanusantara.com',
                'address'     => 'Jl. Pahlawan No. 12, Sidoarjo',
                'is_active'   => true,
            ],
            [
                'code'        => 'SUP-003',
                'name'        => 'PT Maju Makmur Teknik',
                'contact_person' => 'Agus Wijaya',
                'phone'       => '021-8877665',
                'email'       => 'procurement@majumakmur.id',
                'address'     => 'Kawasan MM2100, Bekasi',
                'is_active'   => true,
            ],
            [
                'code'        => 'SUP-004',
                'name'        => 'PT Pneumatic Sistem Indonesia',
                'contact_person' => 'Rina Kusuma',
                'phone'       => '021-5544332',
                'email'       => 'sales@pneumatic-si.co.id',
                'address'     => 'Jl. Gatot Subroto Kav. 88, Jakarta',
                'is_active'   => true,
            ],
            [
                'code'        => 'SUP-005',
                'name'        => 'UD Sumber Teknik Surabaya',
                'contact_person' => 'Hendra Setiawan',
                'phone'       => '031-3344556',
                'email'       => 'hendra@sumberteknik.com',
                'address'     => 'Jl. Kapas Krampung No. 77, Surabaya',
                'is_active'   => true,
            ],
        ];

        foreach ($suppliers as $data) {
            Supplier::create($data);
        }

        $this->command->info('✅ ' . count($suppliers) . ' supplier berhasil di-seed.');
    }
}
