<?php

namespace App\Services;

use App\Models\Cell;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class FifoPickingService
{
    /**
     * Preview FIFO allocation tanpa mengubah data.
     * Return array of picks, atau throw Exception jika stok kurang.
     */
    public function preview(int $itemId, int $warehouseId, int $requestedQty): array
    {
        $stocks = Stock::with(['cell.rack.zone'])
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'available')
            ->where('quantity', '>', 0)
            ->orderBy('inbound_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        $remaining = $requestedQty;
        $picks     = [];

        foreach ($stocks as $stock) {
            if ($remaining <= 0) break;

            $takeQty = min($remaining, $stock->quantity);
            $picks[] = [
                'stock_id'     => $stock->id,
                'cell_id'      => $stock->cell_id,
                'cell_code'    => $stock->cell?->code ?? '–',
                'rack_code'    => $stock->cell?->rack?->code ?? '–',
                'zone_code'    => $stock->cell?->rack?->zone?->code ?? '–',
                'inbound_date' => $stock->inbound_date?->format('d M Y') ?? '–',
                'lpn'          => $stock->lpn,
                'available_qty'=> $stock->quantity,
                'take_qty'     => $takeQty,
            ];
            $remaining -= $takeQty;
        }

        if ($remaining > 0) {
            $available = $requestedQty - $remaining;
            throw new \Exception(
                "Stok tidak mencukupi. Tersedia: {$available}, dibutuhkan: {$requestedQty}, kekurangan: {$remaining}."
            );
        }

        return $picks;
    }

    /**
     * Konfirmasi FIFO picking: kurangi stok, update cell, catat movement.
     */
    public function confirm(int $itemId, int $warehouseId, int $requestedQty, ?string $notes = null): array
    {
        // Preview outside the transaction to build the pick plan.
        // Inside the transaction we re-fetch with lockForUpdate() so concurrent
        // requests cannot read the same stock rows simultaneously (SELECT ... FOR UPDATE).
        $previewPicks = $this->preview($itemId, $warehouseId, $requestedQty);
        $stockIds     = array_column($previewPicks, 'stock_id');

        $confirmedPicks = [];

        DB::transaction(function () use (
            $stockIds, $itemId, $warehouseId, $requestedQty, $notes, &$confirmedPicks
        ) {
            // Re-acquire rows with an exclusive lock, ordered FIFO.
            $stocks = Stock::whereIn('id', $stockIds)
                ->where('status', 'available')
                ->where('quantity', '>', 0)
                ->lockForUpdate()
                ->orderBy('inbound_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();

            // Re-calculate allocation against freshly locked quantities.
            $remaining = $requestedQty;
            foreach ($stocks as $stock) {
                if ($remaining <= 0) break;

                $take = min($remaining, $stock->quantity);
                $remaining -= $take;

                if ($take >= $stock->quantity) {
                    $stock->update([
                        'quantity'      => 0,
                        'status'        => 'consumed',
                        'last_moved_at' => now(),
                    ]);
                } else {
                    $stock->update([
                        'quantity'      => $stock->quantity - $take,
                        'last_moved_at' => now(),
                    ]);
                }

                $cell = Cell::find($stock->cell_id);
                if ($cell) {
                    $cell->capacity_used = max(0, $cell->capacity_used - $take);
                    $cell->save();
                    $cell->updateStatus();
                }

                StockMovement::create([
                    'item_id'        => $itemId,
                    'warehouse_id'   => $warehouseId,
                    'from_cell_id'   => $stock->cell_id,
                    'to_cell_id'     => null,
                    'performed_by'   => auth()->id(),
                    'lpn'            => $stock->lpn,
                    'batch_no'       => $stock->batch_no,
                    'quantity'       => $take,
                    'movement_type'  => 'outbound',
                    'reference_type' => 'FIFO_PICKING',
                    'reference_id'   => null,
                    'notes'          => $notes,
                    'moved_at'       => now(),
                ]);

                $confirmedPicks[] = [
                    'stock_id'     => $stock->id,
                    'cell_id'      => $stock->cell_id,
                    'cell_code'    => $cell?->code ?? '–',
                    'inbound_date' => $stock->inbound_date?->format('d M Y') ?? '–',
                    'take_qty'     => $take,
                ];
            }

            // If concurrent requests consumed stock between preview and lock,
            // remaining will be > 0 here — roll back and surface a clear error.
            if ($remaining > 0) {
                $available = $requestedQty - $remaining;
                throw new \Exception(
                    "Stok berubah saat proses konfirmasi (concurrency). Tersedia saat ini: {$available}, dibutuhkan: {$requestedQty}. Silakan ulangi preview."
                );
            }
        });

        return $confirmedPicks;
    }
}
