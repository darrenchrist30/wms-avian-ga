<?php

namespace App\Notifications;

use App\Models\InboundOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GaPendingReviewNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly InboundOrder $order,
        public readonly string $triggeredBy,
        public readonly float $fitnessScore,
        public readonly string $reviewReason
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'          => 'ga_pending_review',
            'icon'          => 'fas fa-exclamation-triangle',
            'color'         => 'warning',
            'title'         => 'Rekomendasi GA Perlu Review',
            'message'       => "DO <strong>{$this->order->do_number}</strong> memerlukan review Supervisor. "
                             . "Fitness: <strong>" . number_format($this->fitnessScore, 1) . "</strong>/100. "
                             . "Alasan: {$this->reviewReason}",
            'url'           => route('inbound.orders.show', $this->order->id),
            'order_id'      => $this->order->id,
            'do_number'     => $this->order->do_number,
            'fitness_score' => $this->fitnessScore,
            'review_reason' => $this->reviewReason,
        ];
    }
}
