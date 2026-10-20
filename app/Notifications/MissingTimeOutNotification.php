<?php

namespace App\Notifications;

use App\Models\Attendance;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MissingTimeOutNotification extends Notification
{
    use Queueable;

    public function __construct(public Attendance $attendance)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'missing_time_out',
            'attendance_id' => $this->attendance->id,
            'attendance_date' => $this->attendance->date->toDateString(),
            'title' => 'Missing Time Out Detected',
            'message' => "You didn't mark Time Out on {$this->attendance->date->toFormattedDateString()}. ".
                'That day has been marked incomplete — please submit an attendance correction request if needed.',
        ];
    }
}
