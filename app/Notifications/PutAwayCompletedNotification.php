<?php

namespace App\Notifications;

use App\Models\InboundOrder;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PutAwayCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly InboundOrder $order,
        public readonly int $totalItems,
        public readonly int $totalQty,
        public readonly ?User $operator = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $operatorName = $this->operator?->name ?? 'Operator';
        $warehouse    = $this->order->warehouse?->name ?? '';

        return [
            'type'        => 'putaway_completed',
            'icon'        => 'fas fa-check-double',
            'color'       => 'success',
            'title'       => 'Put-Away Selesai',
            'message'     => "DO <strong>{$this->order->do_number}</strong> · "
                           . "<strong>{$this->totalItems} item</strong> ({$this->totalQty} unit) "
                           . "ditempatkan oleh <strong>{$operatorName}</strong>"
                           . ($warehouse ? " · {$warehouse}" : ''),
            'url'         => route('putaway.show', $this->order->id, false),
            'order_id'    => $this->order->id,
            'do_number'   => $this->order->do_number,
            'total_items' => $this->totalItems,
            'total_qty'   => $this->totalQty,
            'operator'    => $operatorName,
        ];
    }
}
