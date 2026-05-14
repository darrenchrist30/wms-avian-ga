<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewInboundBatchNotification extends Notification
{
    use Queueable;

    /**
     * @param  array  $orders   [['id' => int, 'do_number' => string], ...]
     */
    public function __construct(
        public readonly array $orders
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $count = count($this->orders);

        return [
            'type'    => 'new_inbound_batch',
            'icon'    => 'fas fa-truck-loading',
            'color'   => 'primary',
            'title'   => 'Inbound Baru Masuk',
            'message' => "{$count} DO baru masuk dari ERP — menunggu proses GA.",
            'url'     => route('inbound.orders.index') . '?status=inbound',
        ];
    }
}
