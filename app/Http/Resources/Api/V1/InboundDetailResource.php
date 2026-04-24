<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Format satu baris item dalam inbound transaction.
 */
class InboundDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'sku'               => $this->item?->sku,
            'erp_item_code'     => $this->item?->erp_item_code,
            'item_name'         => $this->item?->name,
            'lpn'               => $this->lpn,
            'quantity_ordered'  => $this->quantity_ordered,
            'quantity_received' => $this->quantity_received,
            'status'            => $this->status,
            'status_label'      => $this->statusLabel(),
            'notes'             => $this->notes,
        ];
    }

    private function statusLabel(): string
    {
        return match($this->status) {
            'pending'     => 'Menunggu Put-Away',
            'recommended' => 'Rekomendasi Tersedia',
            'put_away'    => 'Sudah Disimpan',
            'partial'     => 'Sebagian Disimpan',
            default       => $this->status,
        };
    }
}
