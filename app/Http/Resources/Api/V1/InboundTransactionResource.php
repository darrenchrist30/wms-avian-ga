<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Format lengkap satu inbound transaction beserta item-itemnya.
 *
 * Digunakan sebagai response untuk:
 * - POST /api/v1/inbound/receive
 * - GET  /api/v1/inbound/{doNumber}
 * - GET  /api/v1/inbound (dalam collection)
 */
class InboundTransactionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'do_number'     => $this->do_number,
            'status'        => $this->status,
            'status_label'  => $this->statusLabel(),

            // Tanggal & waktu
            'do_date'       => $this->do_date?->format('Y-m-d'),
            'received_at'   => $this->received_at?->toIso8601String(),
            'processed_at'  => $this->processed_at?->toIso8601String(),

            // Referensi dokumen
            'erp_reference'  => $this->erp_reference,
            'ref_doc_spk'    => $this->ref_doc_spk,
            'batch_header'   => $this->batch_header,
            'no_bukti_manual'=> $this->no_bukti_manual,
            'notes'          => $this->notes,

            // Relasi (hanya disertakan jika sudah di-load dengan eager loading)
            'warehouse' => $this->whenLoaded('warehouse', fn() => [
                'code' => $this->warehouse->code,
                'name' => $this->warehouse->name,
            ]),

            // Ringkasan item
            'summary' => [
                'total_skus'        => $this->whenLoaded('items', fn() => $this->items->count(), 0),
                'total_qty_ordered' => $this->whenLoaded('items', fn() => $this->items->sum('quantity_ordered'), 0),
                'total_qty_pending' => $this->whenLoaded('items',
                    fn() => $this->items->where('status', 'pending')->sum('quantity_ordered'), 0
                ),
                'total_qty_done'    => $this->whenLoaded('items', fn() => $this->items->sum('quantity_received'), 0),
            ],

            // Detail item (hanya disertakan jika di-load)
            'items' => InboundDetailResource::collection($this->whenLoaded('items')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function statusLabel(): string
    {
        return match($this->status) {
            'inbound'   => 'Diterima — Menunggu Proses GA',
            'put_away'  => 'Sedang Put-Away oleh Operator',
            'completed' => 'Selesai — Semua Item Tersimpan',
            default     => $this->status,
        };
    }
}
