<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Lonceng notifikasi (Laravel database notification: tabel `notifications`).
 * $user->notifications / unreadNotifications berasal dari trait Notifiable di model User.
 */
class NotificationController extends Controller
{
    /** JSON untuk dropdown lonceng (di-polling tiap 60 detik oleh wms.js). */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->limit(15)->get()->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? 'Notifikasi',
                'message' => $n->data['message'] ?? '',
                'level' => $n->data['level'] ?? 'primary',
                'icon' => $n->data['icon'] ?? 'bell',
                'read' => $n->read_at !== null,
                'time' => $n->created_at->diffForHumans(),
                'open_url' => route('notifications.open', $n->id),
            ]),
        ]);
    }

    /** Klik notifikasi: tandai dibaca lalu buka halaman terkait. */
    public function open(Request $request, string $id): RedirectResponse
    {
        $n = $request->user()->notifications()->findOrFail($id);
        $n->markAsRead();

        return redirect($n->data['url'] ?? route('dashboard'));
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'Semua notifikasi ditandai dibaca.']);
    }
}
