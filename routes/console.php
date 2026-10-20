<?php

use App\Notifications\AttendanceReminderNotification;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Time In reminder every weekday morning for employees who haven't checked in.
Schedule::call(function () {
    $today = now()->toDateString();
    $checkedInUserIds = Attendance::whereDate('date', $today)->whereNotNull('time_in')->pluck('user_id');

    User::where('status', 'active')
        ->whereNotIn('id', $checkedInUserIds)
        ->each(fn (User $user) => $user->notify(new AttendanceReminderNotification('time_in')));
})->weekdays()->at('09:15');

// Time Out reminder in the evening for employees who checked in but not out.
Schedule::call(function () {
    $today = now()->toDateString();

    Attendance::whereDate('date', $today)
        ->whereNotNull('time_in')
        ->whereNull('time_out')
        ->with('user')
        ->get()
        ->each(fn (Attendance $a) => $a->user->notify(new AttendanceReminderNotification('time_out')));
})->weekdays()->at('18:30');
