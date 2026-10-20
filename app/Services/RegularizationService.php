<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Regularization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegularizationService
{
    public function apply(User $user, array $data): Regularization
    {
        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', $data['attendance_date'])
            ->first();

        return Regularization::create([
            'user_id' => $user->id,
            'attendance_id' => $attendance?->id,
            'attendance_date' => $data['attendance_date'],
            'requested_time_in' => $data['requested_time_in'] ?? null,
            'requested_time_out' => $data['requested_time_out'] ?? null,
            'reason' => $data['reason'],
            'attachment_path' => $data['attachment_path'] ?? null,
            'status' => 'pending',
        ]);
    }

    public function review(Regularization $regularization, User $reviewer, string $action, ?string $remarks = null, array $overrides = []): Regularization
    {
        if ($regularization->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'This request has already been reviewed.']);
        }

        return DB::transaction(function () use ($regularization, $reviewer, $action, $remarks, $overrides) {
            $regularization->reviewed_by = $reviewer->id;
            $regularization->reviewed_at = now();
            $regularization->review_remarks = $remarks;

            // Manager/Admin edited the requested date/time before approving.
            if (! empty($overrides['attendance_date'])) {
                $regularization->attendance_date = $overrides['attendance_date'];
            }
            if (! empty($overrides['requested_time_in'])) {
                $regularization->requested_time_in = $overrides['requested_time_in'];
            }
            if (! empty($overrides['requested_time_out'])) {
                $regularization->requested_time_out = $overrides['requested_time_out'];
            }

            if ($action === 'approve') {
                $regularization->status = 'approved';

                $attendance = Attendance::firstOrNew([
                    'user_id' => $regularization->user_id,
                    'date' => $regularization->attendance_date,
                ]);

                if ($regularization->requested_time_in) {
                    $attendance->time_in = $regularization->requested_time_in;
                }
                if ($regularization->requested_time_out) {
                    $attendance->time_out = $regularization->requested_time_out;
                }

                if ($attendance->time_in && $attendance->time_out) {
                    $attendance->working_hours = round(
                        $attendance->time_in->diffInMinutes($attendance->time_out) / 60, 2
                    );
                }

                $attendance->status = 'present';
                $attendance->is_regularized = true;
                $attendance->missing_time_out = false;
                $attendance->save();

                $regularization->attendance_id = $attendance->id;
            } else {
                $regularization->status = 'rejected';
                // Per company policy, a rejected regularization may result in
                // the day being counted as leave-deducted / absent. Left to
                // downstream payroll/leave logic to action separately.
            }

            $regularization->save();

            return $regularization;
        });
    }
}
