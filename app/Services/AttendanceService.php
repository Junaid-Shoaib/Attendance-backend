<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\CompanySetting;
use App\Models\Holiday;
use App\Models\User;
use App\Notifications\MissingTimeOutNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(
        private AttendanceSecurityService $security,
        private GeofenceService $geofence,
    ) {
    }

    public function timeIn(User $user, array $payload, string $ip): Attendance
    {
        $today = Carbon::today();

        return DB::transaction(function () use ($user, $payload, $ip, $today) {
            $attendance = Attendance::where('user_id', $user->id)->where('date', $today)->lockForUpdate()->first();

            if ($attendance && $attendance->time_in) {
                throw ValidationException::withMessages([
                    'time_in' => 'You have already marked Time In for today.',
                ]);
            }

            // Forgotten Time Out handling: before creating today's record,
            // find any previous day(s) where the employee marked Time In
            // but never marked Time Out (can be more than one consecutive
            // day). Each is closed out as 'incomplete' rather than ever
            // letting today's Time Out attach to a stale record, and the
            // employee is notified so they can submit a correction via the
            // existing regularization flow. This never touches today's row.
            $this->closeOutStaleOpenAttendances($user, $today);

            $result = $this->security->validate($user, $payload);

            $attendance = $attendance ?: new Attendance(['user_id' => $user->id, 'date' => $today]);

            $checkInTime = now();
            $graceMinutes = (int) CompanySetting::get('attendance.late_grace_minutes', config('attendance.working_hours.late_grace_minutes', 15));
            $fullTimeStart = CompanySetting::get('attendance.full_time_start', '09:00');
            $standardStart = $today->copy()->setTimeFromTimeString($fullTimeStart.':00')->addMinutes($graceMinutes);

            $attendance->fill([
                'time_in' => $checkInTime,
                'time_in_latitude' => $payload['latitude'],
                'time_in_longitude' => $payload['longitude'],
                'time_in_address' => $payload['address'] ?? null,
                'time_in_selfie' => $payload['selfie_path'] ?? null,
                'time_in_device_id' => $result['device']->id,
                'time_in_ip' => $ip,
                'time_in_mock_location_flag' => (bool) ($payload['is_mock_location'] ?? false),
                'time_in_distance_meters' => $result['distance'],
                'office_id' => $result['office']?->id,
                'status' => $checkInTime->greaterThan($standardStart) ? 'late' : 'present',
                'missing_time_out' => false,
            ]);

            $attendance->save();

            return $attendance->fresh();
        });
    }

    public function timeOut(User $user, array $payload, string $ip): Attendance
    {
        $today = Carbon::today();

        return DB::transaction(function () use ($user, $payload, $ip, $today) {
            $attendance = Attendance::where('user_id', $user->id)->where('date', $today)->lockForUpdate()->first();

            if (! $attendance || ! $attendance->time_in) {
                throw ValidationException::withMessages([
                    'time_out' => 'You must Time In before you can Time Out.',
                ]);
            }

            if ($attendance->time_out) {
                throw ValidationException::withMessages([
                    'time_out' => 'You have already marked Time Out for today.',
                ]);
            }

            $result = $this->security->validate($user, $payload);

            $checkOutTime = now();
            $workingHours = round($attendance->time_in->diffInMinutes($checkOutTime) / 60, 2);
            $halfDayThreshold = (float) CompanySetting::get(
                'attendance.half_day_hours',
                config('attendance.working_hours.half_day_threshold_hours', 4)
            );

            $attendance->fill([
                'time_out' => $checkOutTime,
                'time_out_latitude' => $payload['latitude'],
                'time_out_longitude' => $payload['longitude'],
                'time_out_address' => $payload['address'] ?? null,
                'time_out_selfie' => $payload['selfie_path'] ?? null,
                'time_out_device_id' => $result['device']->id,
                'time_out_ip' => $ip,
                'time_out_mock_location_flag' => (bool) ($payload['is_mock_location'] ?? false),
                'time_out_distance_meters' => $result['distance'],
                'working_hours' => $workingHours,
            ]);

            if ($workingHours < $halfDayThreshold && $attendance->status !== 'late') {
                $attendance->status = 'half_day';
            }

            $attendance->save();

            return $attendance->fresh();
        });
    }

    public function isHoliday(Carbon $date): bool
    {
        return Holiday::whereDate('date', $date)->exists()
            || Holiday::where('is_recurring_yearly', true)
                ->whereMonth('date', $date->month)
                ->whereDay('date', $date->day)
                ->exists();
    }

    /**
     * Find every attendance record strictly before $today for this user
     * that has a Time In but no Time Out, and close each one out as
     * 'incomplete' so it stops being "open". This deliberately does NOT
     * set a time_out or guess working hours — the employee (or their
     * manager) resolves it via the existing Regularization flow, which
     * already knows how to safely write requested_time_in/time_out back
     * onto the attendance row on approval.
     */
    private function closeOutStaleOpenAttendances(User $user, Carbon $today): void
    {
        $staleRecords = Attendance::where('user_id', $user->id)
            ->whereDate('date', '<', $today)
            ->whereNotNull('time_in')
            ->whereNull('time_out')
            ->where('status', '!=', 'incomplete')
            ->lockForUpdate()
            ->get();

        foreach ($staleRecords as $stale) {
            $stale->status = 'incomplete';
            $stale->missing_time_out = true;
            $stale->save();

            $user->notify(new MissingTimeOutNotification($stale));
        }
    }
}
