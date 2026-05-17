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
            'type'    => 'new_inbound',
            'icon'    => 'fas fa-truck-loading',
            'color'   => 'primary',
            'title'   => 'Inbound Baru Masuk',
            'message' => '1 DO baru masuk dari ERP — menunggu proses GA.',
            'url'     => route('inbound.orders.index', ['status' => 'inbound'], false),
        ];
    }
}
