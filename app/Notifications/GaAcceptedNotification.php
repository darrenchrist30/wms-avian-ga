<?php

namespace App\Notifications;

use App\Models\InboundOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GaAcceptedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly InboundOrder $order,
        public readonly string $acceptedBy,
        public readonly float $fitnessScore
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'          => 'ga_accepted',
            'icon'          => 'fas fa-dolly-flatbed',
            'color'         => 'warning',
            'title'         => 'Rekomendasi GA Disetujui — Siap Put-Away',
            'message'       => "DO <strong>{$this->order->do_number}</strong> sudah disetujui oleh "
                             . "<strong>{$this->acceptedBy}</strong>. "
                             . "Fitness GA: <strong>" . number_format($this->fitnessScore, 1) . "</strong>/100. "
                             . "Operator dapat mulai put-away sekarang.",
            'url'           => route('putaway.show', $this->order->id),
            'order_id'      => $this->order->id,
            'do_number'     => $this->order->do_number,
            'fitness_score' => $this->fitnessScore,
        ];
    }
}
