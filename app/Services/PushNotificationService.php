<?php

namespace App\Services;

use App\Models\FcmToken;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around Firebase Cloud Messaging (FCM) HTTP v1 API.
 * Used for: Time In/Out reminders, Leave Approved/Rejected, Attendance
 * Correction Approved, and Company Announcements.
 */
class PushNotificationService
{
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        $tokens = FcmToken::where('user_id', $user->id)->pluck('token');

        foreach ($tokens as $token) {
            $this->send($token, $title, $body, $data);
        }
    }

    protected function send(string $token, string $title, string $body, array $data = []): void
    {
        $projectId = config('services.firebase.project_id');
        $serverKey = config('services.firebase.server_key');

        if (! $projectId || ! $serverKey) {
            Log::info('FCM not configured; skipping push notification.', compact('title', 'body'));

            return;
        }

        try {
            Http::withToken($serverKey)->post(
                "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
                [
                    'message' => [
                        'token' => $token,
                        'notification' => ['title' => $title, 'body' => $body],
                        'data' => $data,
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('FCM push failed: '.$e->getMessage());
        }
    }
}
