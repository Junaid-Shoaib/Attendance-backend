<?php

namespace App\Notifications;

use App\Models\Leave;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveStatusNotification extends Notification
{
    use Queueable;

    public function __construct(public Leave $leave)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Leave Request '.ucfirst($this->leave->status))
            ->line("Your {$this->leave->leaveType->name} request from {$this->leave->start_date->toFormattedDateString()} to {$this->leave->end_date->toFormattedDateString()} has been {$this->leave->status}.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'leave_status',
            'leave_id' => $this->leave->id,
            'status' => $this->leave->status,
            'title' => 'Leave Request '.ucfirst($this->leave->status),
            'message' => "Your leave request has been {$this->leave->status}.",
        ];
    }
}
