<?php
/**
 * Buat test inbound surat jalan untuk demo / testing WMS
 *
 * Usage:
 *   php create_test_inbound.php          → buat 1 surat jalan (5 item)
 *   php create_test_inbound.php --many   → buat 3 surat jalan sekaligus
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$many = in_array('--many', $argv);

// Data master
$warehouseId = DB::table('warehouses')->value('id');
$userId      = DB::table('users')->value('id');
$suppliers   = DB::table('suppliers')->where('is_active', true)->pluck('id')->toArray();

// Pool item aktif yang ada di WMS (punya stok)
$pool = DB::table('items')
    ->join('stock_records', 'items.id', '=', 'stock_records.item_id')
    ->where('items.sku', 'like', '03SP-%')
    ->where('stock_records.quantity', '>', 0)
    ->select('items.id', 'items.sku', 'items.name')
    ->get()
    ->shuffle();

if ($pool->isEmpty()) {
    echo "ERROR: Tidak ada item dengan stok di WMS.\n";
    exit(1);
}

// ── Definisi surat jalan ───────────────────────────────────────────────────
$orders = [
    [
        'do_number'   => 'SJ-TEST-' . date('ymd') . '-001',
        'do_date'     => now()->toDateString(),
        'supplier_id' => $suppliers[0] ?? null,
        'notes'       => 'Testing inbound - Engine Parts & Body Frame',
        'item_count'  => 5,
        'seed'        => 0,   // offset ke pool item
    ],
];

if ($many) {
    $orders[] = [
        'do_number'   => 'SJ-TEST-' . date('ymd') . '-002',
        'do_date'     => now()->subDays(1)->toDateString(),
        'supplier_id' => $suppliers[1] ?? $suppliers[0] ?? null,
        'notes'       => 'Testing inbound - Hydraulic & Electrical',
        'item_count'  => 4,
        'seed'        => 5,
    ];
    $orders[] = [
        'do_number'   => 'SJ-TEST-' . date('ymd') . '-003',
        'do_date'     => now()->subDays(2)->toDateString(),
        'supplier_id' => $suppliers[2] ?? $suppliers[0] ?? null,
        'notes'       => 'Testing inbound - Consumables mixed',
        'item_count'  => 6,
        'seed'        => 9,
    ];
}

echo "=== Create Test Inbound Surat Jalan ===\n\n";

DB::beginTransaction();
try {
    foreach ($orders as $ord) {
        // Cek apakah DO number sudah ada
        if (DB::table('inbound_transactions')->where('do_number', $ord['do_number'])->exists()) {
            echo "[SKIP] {$ord['do_number']} sudah ada di DB.\n";
            continue;
        }

        // Ambil item slice dari pool
        $itemSlice = $pool->slice($ord['seed'], $ord['item_count'])->values();
        if ($itemSlice->isEmpty()) {
            echo "[SKIP] {$ord['do_number']} tidak cukup item di pool.\n";
            continue;
        }

        // Insert header
        $txId = DB::table('inbound_transactions')->insertGetId([
            'warehouse_id'    => $warehouseId,
            'supplier_id'     => $ord['supplier_id'],
            'received_by'     => $userId,
            'do_number'       => $ord['do_number'],
            'no_bukti_manual' => null,
            'erp_reference'   => 'ERP-' . strtoupper(substr($ord['do_number'], -6)),
            'ref_doc_spk'     => null,
            'batch_header'    => null,
            'do_date'         => $ord['do_date'],
            'received_at'     => now(),
            'processed_at'    => null,
            'status'          => 'draft',
            'notes'           => $ord['notes'],
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // Insert detail items
        $details = [];
        foreach ($itemSlice as $item) {
            $qtyOrdered = rand(2, 20);
            $details[] = [
                'inbound_order_id'  => $txId,
                'item_id'           => $item->id,
                'lpn'               => null,
                'lpn_timestamp'     => null,
                'quantity_ordered'  => $qtyOrdered,
                'quantity_received' => 0,
                'status'            => 'pending',
                'notes'             => null,
                'created_at'        => now(),
                'updated_at'        => now(),
            ];
        }
        DB::table('inbound_details')->insert($details);

        echo "[OK] {$ord['do_number']} dibuat (id={$txId})\n";
        echo "     Tanggal : {$ord['do_date']}\n";
        echo "     Status  : draft\n";
        echo "     Items   : " . count($details) . " item\n";
        foreach ($itemSlice as $item) {
            $qty = collect($details)->firstWhere('item_id', $item->id)['quantity_ordered'];
            echo "       - {$item->sku} | {$item->name} | qty_ordered={$qty}\n";
        }
        echo "\n";
    }

    DB::commit();
    echo "Selesai. Buka /inbound/orders untuk melihat surat jalan.\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
