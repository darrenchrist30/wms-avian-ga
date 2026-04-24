<?php

namespace App\Services;

use App\Models\InboundOrder;
use App\Models\InboundOrderItem;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service: InboundReceiveService
 *
 * Bertanggung jawab memproses data inbound yang masuk dari ERP:
 *   1. Resolve warehouse & supplier dari kode/ID ERP
 *   2. Idempotency check — DO yang sama tidak boleh dibuat dua kali
 *   3. Resolve tiap item dari erp_item_code atau sku
 *   4. Simpan inbound_transactions + inbound_details dalam satu DB transaction
 *   5. Return hasil beserta daftar item yang tidak berhasil di-match (jika ada)
 */
class InboundReceiveService
{
    /**
     * Proses penerimaan inbound dari ERP.
     *
     * @param  array $data  Validated data dari InboundReceiveRequest
     * @return array {
     *   'transaction'     => InboundOrder,
     *   'is_new'          => bool,        // false jika DO sudah ada (idempotent)
     *   'unmatched_items' => array,       // item yang tidak ditemukan di master data
     * }
     * @throws \Exception  Jika semua item tidak ditemukan
     */
    public function receive(array $data): array
    {
        // ── 1. Resolve Warehouse ────────────────────────────────────────────
        // Warehouse HARUS aktif. Jika tidak aktif, tolak inbound.
        $warehouse = Warehouse::where('code', $data['warehouse_code'])
            ->where('is_active', true)
            ->first();

        if (!$warehouse) {
            throw new \Exception(
                "Gudang dengan kode '{$data['warehouse_code']}' tidak aktif atau tidak ditemukan."
            );
        }

        // ── 2. Resolve Supplier (opsional) ──────────────────────────────────
        // Coba erp_vendor_id dulu (lebih akurat), fallback ke supplier code.
        $supplier = null;
        if (!empty($data['supplier_erp_id'])) {
            $supplier = Supplier::where('erp_vendor_id', $data['supplier_erp_id'])
                ->where('is_active', true)
                ->first();
        }
        if (!$supplier && !empty($data['supplier_code'])) {
            $supplier = Supplier::where('code', $data['supplier_code'])
                ->where('is_active', true)
                ->first();
        }

        // ── 3. Idempotency Check ────────────────────────────────────────────
        // Jika DO number sudah ada (termasuk yang di-soft-delete), kembalikan data existing.
        // Ini mencegah duplikasi jika ERP mengirim ulang karena timeout/retry.
        $existing = InboundOrder::withTrashed()
            ->where('do_number', $data['do_number'])
            ->first();

        if ($existing) {
            Log::info('[InboundReceive] DO sudah ada — idempotent response', [
                'do_number' => $data['do_number'],
                'status'    => $existing->status,
            ]);

            return [
                'transaction'    => $existing->load('items.item', 'warehouse', 'supplier'),
                'is_new'         => false,
                'unmatched_items'=> [],
            ];
        }

        // ── 4. Resolve Items ────────────────────────────────────────────────
        // Tiap item dicoba match ke master data WMS.
        // Item yang tidak ditemukan dicatat sebagai "unmatched" — tidak menghentikan proses
        // selama minimal ada 1 item yang berhasil di-match.
        $resolvedLines  = [];
        $unmatchedLines = [];

        foreach ($data['items'] as $index => $line) {
            $item = $this->resolveItem($line);

            if ($item) {
                $resolvedLines[] = [
                    'item'     => $item,
                    'quantity' => (int) $line['quantity'],
                    'lpn'      => $line['lpn'] ?? null,
                    'notes'    => $line['notes'] ?? null,
                ];
            } else {
                $unmatchedLines[] = [
                    'line'          => $index + 1,
                    'erp_item_code' => $line['erp_item_code'] ?? null,
                    'sku'           => $line['sku'] ?? null,
                    'quantity'      => $line['quantity'],
                    'reason'        => 'Item tidak ditemukan di master data WMS. '
                                     . 'Pastikan item sudah terdaftar dengan erp_item_code atau sku yang benar.',
                ];
            }
        }

        // Tolak jika 100% item tidak ketemu — tidak ada yang bisa diproses
        if (empty($resolvedLines)) {
            throw new \Exception(
                'Semua item dalam DO tidak ditemukan di master data WMS. '
                . 'Inbound tidak dapat diproses. Daftarkan item terlebih dahulu atau periksa kode item.'
            );
        }

        // ── 5. Simpan ke Database ───────────────────────────────────────────
        // Seluruh proses simpan dibungkus dalam satu DB transaction.
        // Jika ada error di tengah (mis. FK violation), semua rollback otomatis.
        $inbound = DB::transaction(function () use (
            $data, $warehouse, $supplier, $resolvedLines
        ) {
            // Buat header transaksi inbound
            $inbound = InboundOrder::create([
                'warehouse_id'  => $warehouse->id,
                'supplier_id'   => $supplier?->id,
                'do_number'     => $data['do_number'],
                'do_date'       => $data['do_date'],
                'erp_reference' => $data['erp_reference'] ?? null,
                'ref_doc_spk'   => $data['ref_doc_spk'] ?? null,
                'batch_header'  => $data['batch_header'] ?? null,
                'notes'         => $data['notes'] ?? null,
                'received_at'   => now(),
                'status'        => 'draft',
            ]);

            // Buat baris detail per item
            foreach ($resolvedLines as $line) {
                InboundOrderItem::create([
                    'inbound_order_id'  => $inbound->id,
                    'item_id'           => $line['item']->id,
                    'lpn'               => $line['lpn'],
                    'quantity_ordered'  => $line['quantity'],
                    'quantity_received' => 0,
                    'status'            => 'pending',
                    'notes'             => $line['notes'],
                ]);
            }

            return $inbound;
        });

        // Eager load relasi setelah simpan
        $inbound->load('items.item', 'warehouse', 'supplier');

        Log::info('[InboundReceive] DO baru berhasil diterima dari ERP', [
            'do_number'       => $data['do_number'],
            'transaction_id'  => $inbound->id,
            'warehouse'       => $warehouse->code,
            'supplier'        => $supplier?->code ?? 'N/A',
            'total_resolved'  => count($resolvedLines),
            'total_unmatched' => count($unmatchedLines),
        ]);

        return [
            'transaction'    => $inbound,
            'is_new'         => true,
            'unmatched_items'=> $unmatchedLines,
        ];
    }

    /**
     * Resolve item dari master data berdasarkan erp_item_code atau sku.
     *
     * Urutan prioritas:
     *   1. erp_item_code — kode item di sistem ERP (lebih reliable, tidak berubah)
     *   2. sku           — fallback jika erp_item_code tidak tersedia
     *
     * Hanya item aktif (is_active = true) yang akan di-match.
     */
    private function resolveItem(array $line): ?Item
    {
        if (!empty($line['erp_item_code'])) {
            $item = Item::where('erp_item_code', $line['erp_item_code'])
                ->where('is_active', true)
                ->first();

            if ($item) {
                return $item;
            }
        }

        if (!empty($line['sku'])) {
            return Item::where('sku', $line['sku'])
                ->where('is_active', true)
                ->first();
        }

        return null;
    }
}
