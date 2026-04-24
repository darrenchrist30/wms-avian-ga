<?php

namespace App\Notifications;

use App\Models\InboundOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PutAwayCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly InboundOrder $order,
        public readonly int $totalItems,
        public readonly int $totalQty
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'        => 'putaway_completed',
            'icon'        => 'fas fa-check-double',
            'color'       => 'success',
            'title'       => 'Put-Away Selesai!',
            'message'     => "DO <strong>{$this->order->do_number}</strong> — semua "
                           . "<strong>{$this->totalItems} item</strong> ({$this->totalQty} unit) "
                           . "berhasil di-put-away. Stok gudang telah diperbarui.",
            'url'         => route('putaway.show', $this->order->id),
            'order_id'    => $this->order->id,
            'do_number'   => $this->order->do_number,
            'total_items' => $this->totalItems,
            'total_qty'   => $this->totalQty,
        ];
    }
}
