<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Seeder: 10 DO inbound test untuk pengujian Black-Box TC-05 s/d TC-22.
 *
 * Jalankan dengan:
 *   php artisan db:seed --class=TestInboundSeeder
 *
 * Hapus data test:
 *   php artisan db:seed --class=TestInboundSeeder --rollback
 *   (atau truncate manual lewat tinker)
 */
class TestInboundSeeder extends Seeder
{
    public function run(): void
    {
        $warehouseId  = DB::table('warehouses')->value('id');
        $receivedById = DB::table('users')->value('id');
        $now          = Carbon::now();

        // Ambil 50 item aktif acak untuk distribusi ke 10 DO
        $items = DB::table('items')
            ->where('is_active', 1)
            ->inRandomOrder()
            ->limit(50)
            ->get(['id', 'sku', 'name']);

        if ($items->isEmpty()) {
            $this->command->error('Tidak ada item aktif di database.');
            return;
        }

        /**
         * 10 DO dengan variasi:
         *  DO-TEST-001  3 item   — normal
         *  DO-TEST-002  5 item   — normal, berbeda kategori
         *  DO-TEST-003  2 item   — qty besar (stress kapasitas)
         *  DO-TEST-004  7 item   — banyak item
         *  DO-TEST-005  4 item   — tanggal lama (FIFO test)
         *  DO-TEST-006  6 item   — item berulang dari DO lain (co-occurrence)
         *  DO-TEST-007  3 item   — normal
         *  DO-TEST-008  5 item   — normal
         *  DO-TEST-009  4 item   — normal
         *  DO-TEST-010  8 item   — paling banyak item sekaligus
         */
        $doConfigs = [
            ['do' => 'DO-TEST-001', 'count' => 3, 'days_ago' => 0,  'notes' => 'Test TC-07: Input inbound normal 3 item'],
            ['do' => 'DO-TEST-002', 'count' => 5, 'days_ago' => 0,  'notes' => 'Test TC-08: 5 item berbeda kategori'],
            ['do' => 'DO-TEST-003', 'count' => 2, 'days_ago' => 1,  'notes' => 'Test TC-19: Qty besar stress kapasitas'],
            ['do' => 'DO-TEST-004', 'count' => 7, 'days_ago' => 1,  'notes' => 'Test TC-11: GA dengan 7 item'],
            ['do' => 'DO-TEST-005', 'count' => 4, 'days_ago' => 10, 'notes' => 'Test FIFO: DO lama, tanggal lebih awal'],
            ['do' => 'DO-TEST-006', 'count' => 6, 'days_ago' => 2,  'notes' => 'Test TC-22: Co-occurrence affinity'],
            ['do' => 'DO-TEST-007', 'count' => 3, 'days_ago' => 0,  'notes' => 'Test TC-20: Konfirmasi put-away sesuai rekomendasi'],
            ['do' => 'DO-TEST-008', 'count' => 5, 'days_ago' => 0,  'notes' => 'Test TC-21: Put-away beda cell dari rekomendasi'],
            ['do' => 'DO-TEST-009', 'count' => 4, 'days_ago' => 3,  'notes' => 'Test TC-14: Lihat rekomendasi + highlight 3D'],
            ['do' => 'DO-TEST-010', 'count' => 8, 'days_ago' => 0,  'notes' => 'Test TC-32: DO terbesar — 8 item sekaligus'],
        ];

        $itemPool  = $items->values();
        $poolSize  = $itemPool->count();
        $itemIndex = 0;

        foreach ($doConfigs as $cfg) {
            // Skip jika DO number sudah ada
            if (DB::table('inbound_transactions')->where('do_number', $cfg['do'])->exists()) {
                $this->command->warn("DO {$cfg['do']} sudah ada, dilewati.");
                continue;
            }

            $doDate     = $now->copy()->subDays($cfg['days_ago'])->toDateString();
            $receivedAt = $now->copy()->subDays($cfg['days_ago'])->toDateTimeString();

            $orderId = DB::table('inbound_transactions')->insertGetId([
                'warehouse_id'  => $warehouseId,
                'received_by'   => $receivedById,
                'do_number'     => $cfg['do'],
                'erp_reference' => 'ERP-' . strtoupper(substr(md5($cfg['do']), 0, 8)),
                'do_date'       => $doDate,
                'received_at'   => $receivedAt,
                'status'        => 'inbound',
                'notes'         => $cfg['notes'],
                'created_at'    => $receivedAt,
                'updated_at'    => $receivedAt,
            ]);

            $details = [];
            for ($i = 0; $i < $cfg['count']; $i++) {
                $item = $itemPool[$itemIndex % $poolSize];
                $itemIndex++;

                // Qty bervariasi: DO-TEST-003 qty besar, sisanya normal
                $qtyOrdered = ($cfg['do'] === 'DO-TEST-003') ? rand(80, 120) : rand(3, 20);

                $details[] = [
                    'inbound_order_id'  => $orderId,
                    'item_id'           => $item->id,
                    'lpn'               => 'LPN-' . $cfg['do'] . '-' . str_pad($i + 1, 2, '0', STR_PAD_LEFT),
                    'lpn_timestamp'     => $receivedAt,
                    'quantity_ordered'  => $qtyOrdered,
                    'quantity_received' => $qtyOrdered,
                    'status'            => 'pending',
                    'notes'             => null,
                    'created_at'        => $receivedAt,
                    'updated_at'        => $receivedAt,
                ];
            }

            DB::table('inbound_details')->insert($details);

            $this->command->info("✓ {$cfg['do']} — {$cfg['count']} item | {$cfg['notes']}");
        }

        $this->command->newLine();
        $this->command->info('Selesai. 10 DO test siap di halaman Inbound.');
        $this->command->line('Untuk hapus: DB::table("inbound_transactions")->where("do_number","like","DO-TEST-%")->delete();');
    }
}
