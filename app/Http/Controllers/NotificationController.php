<?php

namespace App\Http\Controllers;

use App\Models\InboundOrder;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    // GET /notifications — daftar notifikasi user (JSON, untuk dropdown)
    public function index()
    {
        $user = auth()->user();

        $notifications = $user
            ->notifications()
            ->latest()
            ->take(15)
            ->get()
            ->map(fn($n) => [
                'id'         => $n->id,
                'type'       => $n->data['type']       ?? 'info',
                'icon'       => $n->data['icon']       ?? 'fas fa-bell',
                'color'      => $n->data['color']      ?? 'secondary',
                'title'      => $n->data['title']      ?? 'Notifikasi',
                'message'    => $n->data['message']    ?? '',
                'url'        => $n->data['url']        ?? '#',
                'is_read'    => !is_null($n->read_at),
                'created_at' => $n->created_at->diffForHumans(),
            ]);

        $unreadCount = $user->unreadNotifications()->count();

        // Sidebar badge counts — pending action per menu
        $sidebarCounts = $this->getSidebarCounts($user);

        return response()->json([
            'notifications'  => $notifications,
            'unread_count'   => $unreadCount,
            'sidebar_counts' => $sidebarCounts,
        ]);
    }

    private function getSidebarCounts($user): array
    {
        $counts = [];

        // DO menunggu konfirmasi qty (status: draft, sudah ada items)
        $counts['inbound_draft'] = InboundOrder::where('status', 'draft')
            ->whereHas('items')
            ->count();

        // DO siap put-away (status: recommended atau put_away)
        $counts['putaway_pending'] = InboundOrder::whereIn('status', ['recommended', 'put_away'])
            ->whereHas('gaRecommendations', fn($q) => $q->where('status', 'accepted'))
            ->count();

        return $counts;
    }

    // POST /notifications/{id}/read — tandai satu notifikasi sudah dibaca
    public function markRead(string $id)
    {
        $notification = auth()->user()
            ->notifications()
            ->findOrFail($id);

        $notification->markAsRead();

        return response()->json(['status' => 'success']);
    }

    // POST /notifications/read-all — tandai semua sudah dibaca
    public function markAllRead()
    {
        auth()->user()->unreadNotifications->markAsRead();

        return response()->json([
            'status'  => 'success',
            'message' => 'Semua notifikasi ditandai sudah dibaca.',
        ]);
    }
}
