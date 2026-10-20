<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AttendanceReminderNotification extends Notification
{
    use Queueable;

    public function __construct(public string $reminderType)
    {
        // 'time_in' or 'time_out'
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $message = $this->reminderType === 'time_in'
            ? "Don't forget to mark your Time In for today."
            : "Don't forget to mark your Time Out before you leave.";

        return [
            'type' => 'attendance_reminder',
            'reminder_type' => $this->reminderType,
            'title' => 'Attendance Reminder',
            'message' => $message,
        ];
    }
}
