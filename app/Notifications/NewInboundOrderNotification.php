<?php

namespace App\Notifications;

use App\Models\InboundOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewInboundOrderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly InboundOrder $order
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'       => 'new_inbound',
            'icon'       => 'fas fa-truck',
            'color'      => 'primary',
            'title'      => 'DO Baru Masuk dari ERP',
            'message'    => "DO <strong>{$this->order->do_number}</strong> diterima dari ERP — "
                          . ($this->order->supplier?->name ?? 'Supplier tidak diketahui')
                          . " ({$this->order->items()->count()} item).",
            'url'        => route('inbound.orders.show', $this->order->id),
            'order_id'   => $this->order->id,
            'do_number'  => $this->order->do_number,
        ];
    }
}
