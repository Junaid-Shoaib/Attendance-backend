<?php

namespace App\Notifications;

use App\Models\Regularization;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RegularizationStatusNotification extends Notification
{
    use Queueable;

    public function __construct(public Regularization $regularization)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'regularization_status',
            'regularization_id' => $this->regularization->id,
            'status' => $this->regularization->status,
            'title' => 'Attendance Correction '.ucfirst($this->regularization->status),
            'message' => "Your attendance correction request for {$this->regularization->attendance_date->toFormattedDateString()} has been {$this->regularization->status}.",
        ];
    }
}
