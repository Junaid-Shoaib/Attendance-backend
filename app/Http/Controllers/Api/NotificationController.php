<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * GET /api/notifications
     */
    public function index(Request $request)
    {
        return $this->success('OK', $request->user()->notifications()->paginate($request->get('per_page', 20)));
    }

    /**
     * GET /api/notifications/unread-count
     */
    public function unreadCount(Request $request)
    {
        return $this->success('OK', ['count' => $request->user()->unreadNotifications()->count()]);
    }

    /**
     * PATCH /api/notifications/{id}/read
     */
    public function markAsRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return $this->success('Notification marked as read.');
    }

    /**
     * PATCH /api/notifications/read-all
     */
    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return $this->success('All notifications marked as read.');
    }
}
