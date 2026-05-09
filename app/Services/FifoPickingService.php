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
                'zone_code'    => $stock->cell?->rack?->zone?->zone_code ?? '–',
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
        $picks = $this->preview($itemId, $warehouseId, $requestedQty);

        DB::transaction(function () use ($picks, $itemId, $warehouseId, $notes) {
            foreach ($picks as $pick) {
                $stock = Stock::find($pick['stock_id']);
                $cell  = Cell::find($pick['cell_id']);
                $take  = $pick['take_qty'];

                // Kurangi stock record
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

                // Kurangi kapasitas cell dan update status
                if ($cell) {
                    $cell->capacity_used = max(0, $cell->capacity_used - $take);
                    $cell->save();
                    $cell->updateStatus();
                }

                // Catat stock movement
                StockMovement::create([
                    'item_id'        => $itemId,
                    'warehouse_id'   => $warehouseId,
                    'from_cell_id'   => $pick['cell_id'],
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
            }
        });

        return $picks;
    }
}
