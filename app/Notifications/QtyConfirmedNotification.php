<?php

namespace App\Notifications;

use App\Models\InboundOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class QtyConfirmedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly InboundOrder $order,
        public readonly string $confirmedBy
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'      => 'qty_confirmed',
            'icon'      => 'fas fa-clipboard-check',
            'color'     => 'info',
            'title'     => 'Qty Fisik Dikonfirmasi — Siap Proses GA',
            'message'   => "DO <strong>{$this->order->do_number}</strong> — qty fisik sudah dikonfirmasi oleh "
                         . "<strong>{$this->confirmedBy}</strong>. Siap dijalankan Genetic Algorithm.",
            'url'       => route('inbound.orders.show', $this->order->id),
            'order_id'  => $this->order->id,
            'do_number' => $this->order->do_number,
        ];
    }
}
