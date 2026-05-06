<?php

namespace App\Services;

use App\Jobs\RecalculateAffinityJob;
use App\Models\Cell;
use App\Models\GaRecommendation;
use App\Models\GaRecommendationDetail;
use App\Models\InboundOrder;
use App\Models\InboundOrderItem;
use App\Models\PutAwayConfirmation;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\PutAwayCompletedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * PutAwayService — Menangani seluruh logika put-away
 *
 * Tanggung jawab:
 *   1. Konfirmasi penerimaan fisik (quantity_received per item)
 *   2. Konfirmasi penempatan per item via QR scan (atau manual)
 *      ├─ Insert put_away_confirmations
 *      ├─ Insert/Update stock_records
 *      ├─ Insert stock_movements (type: inbound)
 *      ├─ Update cells.capacity_used + cells.status
 *      └─ Update inbound_details.status
 *   3. Cek auto-complete: jika semua item done → complete order
 *   4. Dispatch RecalculateAffinityJob setelah order completed
 */
class PutAwayService
{
    // ─────────────────────────────────────────────────────────────────────────
    // 1. Konfirmasi Qty Fisik di Dock
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Simpan quantity_received aktual dari operator dock.
     *
     * @param  InboundOrder $order
     * @param  array        $quantities  [ inbound_detail_id => qty_received ]
     * @return void
     * @throws \Exception  Jika semua qty = 0 (order bisa di-cancel)
     */
    public function confirmQuantities(InboundOrder $order, array $quantities): void
    {
        DB::transaction(function () use ($order, $quantities) {
            $totalReceived = 0;

            foreach ($quantities as $detailId => $qtyReceived) {
                $detail = InboundOrderItem::where('id', $detailId)
                    ->where('inbound_order_id', $order->id)
                    ->firstOrFail();

                $qty = max(0, (int) $qtyReceived);
                $detail->update(['quantity_received' => $qty]);
                $totalReceived += $qty;
            }

            // Jika semua qty = 0, otomatis cancel order
            if ($totalReceived === 0) {
                $order->update(['status' => 'cancelled']);
                Log::info('[PutAwayService] Order di-cancel karena semua qty_received = 0', [
                    'inbound_order_id' => $order->id,
                ]);
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Konfirmasi Penempatan (QR Scan oleh Operator)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Proses konfirmasi put-away untuk satu inbound detail item.
     *
     * Dipanggil ketika operator scan QR code sel tujuan.
     *
     * @param  InboundOrderItem    $detail          Item yang di-put-away
     * @param  Cell                $cell            Sel tujuan (dari scan QR)
     * @param  int                 $quantityStored  Qty aktual yang diletakkan
     * @param  int                 $userId          Operator yang melakukan
     * @param  GaRecommendationDetail|null $gaDetail  Detail rekomendasi GA (null = manual)
     * @param  string|null         $notes
     * @return PutAwayConfirmation
     * @throws \Exception
     */
    public function confirmPlacement(
        InboundOrderItem     $detail,
        Cell                 $cell,
        int                  $quantityStored,
        int                  $userId,
        ?GaRecommendationDetail $gaDetail = null,
        ?string              $notes = null
    ): PutAwayConfirmation {
        // Validasi: item belum di-put-away
        if ($detail->status === 'put_away') {
            throw new \Exception("Item '{$detail->item->name}' sudah di-put-away sebelumnya.");
        }

        // Validasi: sel masih bisa menerima barang
        if (!in_array($cell->status, ['available', 'partial'])) {
            throw new \Exception("Sel {$cell->code} tidak tersedia (status: {$cell->status}).");
        }

        $remainingCapacity = $cell->capacity_max - $cell->capacity_used;
        if ($quantityStored > $remainingCapacity) {
            throw new \Exception(
                "Kuantitas {$quantityStored} melebihi sisa kapasitas sel {$cell->code} ({$remainingCapacity})."
            );
        }

        // Validasi partial put-away:
        // Qty boleh disimpan bertahap, tetapi total tidak boleh melebihi qty diterima.
        $alreadyStored = (int) $detail->putAwayConfirmations()->sum('quantity_stored');
        $remainingQty  = (int) $detail->quantity_received - $alreadyStored;

        if ($remainingQty <= 0) {
            throw new \Exception("Item '{$detail->item->name}' sudah tersimpan seluruhnya.");
        }

        if ($quantityStored > $remainingQty) {
            throw new \Exception(
                "Qty yang ditempatkan ({$quantityStored}) melebihi sisa qty item ({$remainingQty})."
            );
        }

        // Jika konfirmasi mengikuti detail rekomendasi GA tertentu,
        // qty harus sesuai dengan qty pada detail rekomendasi tersebut.
        if ($gaDetail && $quantityStored !== (int) $gaDetail->quantity) {
            throw new \Exception(
                "Qty yang ditempatkan ({$quantityStored}) harus sesuai dengan qty rekomendasi GA ({$gaDetail->quantity})."
            );
        }

        // Cegah detail rekomendasi GA yang sama dikonfirmasi dua kali
        if ($gaDetail && PutAwayConfirmation::where('ga_recommendation_detail_id', $gaDetail->id)->exists()) {
            throw new \Exception("Rekomendasi ke sel {$gaDetail->cell?->code} sudah pernah dikonfirmasi.");
        }

        // Validasi: cell harus berada di warehouse yang sama dengan inbound order
        $cell->loadMissing('rack.zone');
        $orderWarehouseId = $detail->inboundOrder->warehouse_id;
        $cellWarehouseId  = $cell->rack?->zone?->warehouse_id
            ?? $cell->rack?->warehouse_id
            ?? null;
        if ($cellWarehouseId !== $orderWarehouseId) {
            throw new \Exception(
                "Sel {$cell->code} tidak berada di gudang yang sama dengan order ini. Scan sel dari gudang yang benar."
            );
        }

        // Apakah operator mengikuti rekomendasi GA?
        $followRecommendation = $gaDetail
            ? ($gaDetail->cell_id === $cell->id)
            : false;

        return DB::transaction(function () use (
            $detail,
            $cell,
            $quantityStored,
            $userId,
            $gaDetail,
            $followRecommendation,
            $notes,
            $alreadyStored
        ) {
            // a) Catat konfirmasi
            $confirmation = PutAwayConfirmation::create([
                'inbound_order_item_id'       => $detail->id,
                'cell_id'                     => $cell->id,
                'ga_recommendation_detail_id' => $gaDetail?->id,
                'user_id'                     => $userId,
                'quantity_stored'             => $quantityStored,
                'follow_recommendation'       => $followRecommendation,
                'confirmed_at'               => now(),
                'notes'                       => $notes,
            ]);

            // b) Insert/Update stock_records (FIFO: inbound_date di sini)
            $this->upsertStock($detail, $cell, $quantityStored);

            // c) Catat stock_movement (type: inbound)
            StockMovement::create([
                'item_id'        => $detail->item_id,
                'warehouse_id'   => $detail->inboundOrder->warehouse_id,
                'from_cell_id'   => null,
                'to_cell_id'     => $cell->id,
                'performed_by'   => $userId,
                'lpn'            => $detail->lpn,
                'quantity'       => $quantityStored,
                'movement_type'  => 'inbound',
                'reference_type' => 'InboundOrder',
                'reference_id'   => $detail->inbound_order_id,
                'notes'          => $notes,
                'moved_at'       => now(),
            ]);

            // d) Update kapasitas sel
            $newUsed = $cell->capacity_used + $quantityStored;
            $cell->update(['capacity_used' => $newUsed]);
            $cell->updateStatus(); // auto-update status (available/partial/full)

            // e) Update status item berdasarkan total qty yang sudah disimpan
            $totalStoredAfter = $alreadyStored + $quantityStored;

            $detail->update([
                'status' => $totalStoredAfter >= $detail->quantity_received
                    ? 'put_away'
                    : 'partial_put_away',
            ]);

            // f) Cek apakah seluruh order sudah selesai
            $this->checkAndCompleteOrder($detail->inboundOrder);

            return $confirmation;
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. Resolve Cell dari QR Code
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Cari Cell berdasarkan qr_code yang di-scan operator.
     *
     * @throws \Exception Jika QR tidak ditemukan atau sel tidak aktif
     */
    public function resolveCellByQr(string $qrCode): Cell
    {
        $cell = Cell::where(function ($q) use ($qrCode) {
            $q->where('qr_code', $qrCode)
                ->orWhere('code', $qrCode)
                ->orWhere('label', $qrCode);
        })
            ->where('is_active', true)
            ->with('rack.zone')
            ->first();

        if (!$cell) {
            throw new \Exception("QR Code '{$qrCode}' tidak ditemukan atau sel tidak aktif.");
        }

        return $cell;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Insert atau update stock_records.
     * Jika item + cell + lpn yang sama sudah ada, tambahkan quantity-nya.
     * Jika belum ada, buat record baru (FIFO via inbound_date).
     */
    private function upsertStock(InboundOrderItem $detail, Cell $cell, int $qty): void
    {
        $existing = Stock::where('item_id', $detail->item_id)
            ->where('cell_id', $cell->id)
            ->where('lpn', $detail->lpn)
            ->where('status', 'available')
            ->first();

        if ($existing) {
            $existing->update([
                'quantity'      => $existing->quantity + $qty,
                'last_moved_at' => now(),
            ]);
        } else {
            Stock::create([
                'item_id'               => $detail->item_id,
                'cell_id'               => $cell->id,
                'warehouse_id'          => $detail->inboundOrder->warehouse_id,
                'inbound_order_item_id' => $detail->id,
                'lpn'                   => $detail->lpn,
                'quantity'              => $qty,
                'inbound_date'          => $detail->inboundOrder->do_date ?? today(),
                'last_moved_at'         => now(),
                'status'                => 'available',
            ]);
        }
    }

    /**
     * Cek apakah semua item dalam order sudah put_away.
     * Jika ya, ubah status order → 'completed' dan dispatch affinity job.
     */
    private function checkAndCompleteOrder(InboundOrder $order): void
    {
        $order->loadMissing('items');
        $pendingCount = $order->items->where('status', '!=', 'put_away')->count();

        if ($pendingCount === 0) {
            $order->update(['status' => 'completed']);

            Log::info('[PutAwayService] Order selesai, dispatch RecalculateAffinityJob', [
                'inbound_order_id' => $order->id,
            ]);

            // Notifikasi ke Admin & Supervisor: put-away selesai
            $totalItems = $order->items->count();
            $totalQty   = $order->items->sum('quantity_received');
            $notifUsers = User::whereHas('role', fn($q) => $q->whereIn('slug', ['admin', 'supervisor']))->get();
            Notification::send($notifUsers, new PutAwayCompletedNotification($order, $totalItems, $totalQty));

            // Dispatch async job untuk update item_affinities
            dispatch(new RecalculateAffinityJob($order->id));
        } elseif ($order->status !== 'put_away') {
            $order->update(['status' => 'put_away']);
        }
    }
}
