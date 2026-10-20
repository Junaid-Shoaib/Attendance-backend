<?php

namespace App\Notifications;

use App\Models\Leave;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewLeaveRequestNotification extends Notification
{
    use Queueable;

    public function __construct(public Leave $leave)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_leave_request',
            'leave_id' => $this->leave->id,
            'title' => 'New Leave Request',
            'message' => "{$this->leave->user->name} has requested {$this->leave->total_days} day(s) of leave.",
        ];
    }
}
