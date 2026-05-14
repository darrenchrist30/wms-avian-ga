<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GaBatchAcceptedNotification extends Notification
{
    use Queueable;

    /**
     * @param  int     $count       Jumlah DO yang berhasil diproses GA
     * @param  string  $acceptedBy  Nama user yang menjalankan batch GA
     */
    public function __construct(
        public readonly int    $count,
        public readonly string $acceptedBy
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'    => 'ga_batch_accepted',
            'icon'    => 'fas fa-dolly-flatbed',
            'color'   => 'warning',
            'title'   => 'GA Selesai — Siap Put-Away',
            'message' => "{$this->count} DO sudah diproses GA oleh <strong>{$this->acceptedBy}</strong> dan siap put-away.",
            'url'     => route('putaway.queue'),
        ];
    }
}
