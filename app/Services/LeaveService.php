<?php

namespace App\Services;

use App\Models\Leave;
use App\Models\LeaveBalance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveService
{
    public function apply(User $user, array $data): Leave
    {
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        $totalDays = $data['total_days'] ?? ($start->diffInDaysFiltered(fn (Carbon $d) => ! $d->isWeekend(), $end) + ($start->isWeekend() ? 0 : 1));

        $year = $start->year;
        $balance = LeaveBalance::firstOrCreate(
            ['user_id' => $user->id, 'leave_type_id' => $data['leave_type_id'], 'year' => $year],
            ['allocated_days' => 0, 'used_days' => 0, 'carried_forward_days' => 0]
        );

        if ($balance->remaining_days < $totalDays) {
            throw ValidationException::withMessages([
                'leave_type_id' => 'Insufficient leave balance. Remaining: '.$balance->remaining_days.' day(s).',
            ]);
        }

        return DB::transaction(function () use ($user, $data, $start, $end, $totalDays) {
            return Leave::create([
                'user_id' => $user->id,
                'leave_type_id' => $data['leave_type_id'],
                'start_date' => $start,
                'end_date' => $end,
                'total_days' => $totalDays,
                'reason' => $data['reason'],
                'attachment_path' => $data['attachment_path'] ?? null,
                'status' => 'pending',
                'manager_id' => $user->manager_id,
            ]);
        });
    }

    public function managerAction(Leave $leave, User $manager, string $action, ?string $remarks = null, array $overrides = []): Leave
    {
        if ($leave->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'This leave request has already been actioned.']);
        }

        $this->applyDateOverrides($leave, $overrides);

        if ($action === 'approve') {
            // If no separate admin-approval stage is required, mark fully approved
            // and deduct balance. Otherwise route to admin.
            $requiresAdminApproval = (bool) \App\Models\CompanySetting::get('leave_requires_admin_approval', false);

            $leave->manager_id = $manager->id;
            $leave->manager_action_at = now();
            $leave->manager_remarks = $remarks;
            $leave->status = $requiresAdminApproval ? 'manager_approved' : 'approved';

            if (! $requiresAdminApproval) {
                $this->deductBalance($leave);
            }
        } else {
            $leave->status = 'rejected';
            $leave->manager_action_at = now();
            $leave->rejection_reason = $remarks;
        }

        $leave->save();

        return $leave;
    }

    public function adminAction(Leave $leave, User $admin, string $action, ?string $remarks = null, array $overrides = []): Leave
    {
        if (! in_array($leave->status, ['manager_approved', 'pending'], true)) {
            throw ValidationException::withMessages(['status' => 'This leave request cannot be actioned at its current stage.']);
        }

        $this->applyDateOverrides($leave, $overrides);

        $leave->admin_id = $admin->id;
        $leave->admin_action_at = now();
        $leave->admin_remarks = $remarks;

        if ($action === 'approve') {
            $leave->status = 'approved';
            $this->deductBalance($leave);
        } else {
            $leave->status = 'rejected';
            $leave->rejection_reason = $remarks;
        }

        $leave->save();

        return $leave;
    }

    /**
     * Apply a manager/admin edit of the requested Start/End Date before
     * approval, recalculating total_days the same way apply() does so
     * balance deduction stays correct.
     */
    protected function applyDateOverrides(Leave $leave, array $overrides): void
    {
        if (empty($overrides['start_date']) && empty($overrides['end_date'])) {
            return;
        }

        $start = Carbon::parse($overrides['start_date'] ?? $leave->start_date);
        $end = Carbon::parse($overrides['end_date'] ?? $leave->end_date);

        if ($end->lt($start)) {
            throw ValidationException::withMessages(['end_date' => 'End Date cannot be before Start Date.']);
        }

        $leave->start_date = $start;
        $leave->end_date = $end;
        $leave->total_days = $start->diffInDaysFiltered(fn (Carbon $d) => ! $d->isWeekend(), $end) + ($start->isWeekend() ? 0 : 1);
    }

    protected function deductBalance(Leave $leave): void
    {
        $balance = LeaveBalance::firstOrCreate(
            ['user_id' => $leave->user_id, 'leave_type_id' => $leave->leave_type_id, 'year' => $leave->start_date->year],
            ['allocated_days' => 0, 'used_days' => 0, 'carried_forward_days' => 0]
        );

        $balance->increment('used_days', $leave->total_days);
    }

    public function cancel(Leave $leave): Leave
    {
        if (! in_array($leave->status, ['pending', 'manager_approved'], true)) {
            throw ValidationException::withMessages(['status' => 'Only pending leave requests can be cancelled.']);
        }

        $leave->status = 'cancelled';
        $leave->save();

        return $leave;
    }
}
