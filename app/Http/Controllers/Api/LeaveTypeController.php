<?php

namespace App\Http\Controllers\Api;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Illuminate\Http\Request;

class LeaveTypeController extends Controller
{
    public function index()
    {
        return $this->success('OK', LeaveType::where('is_active', true)->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|unique:leave_types,code',
            'default_days_per_year' => 'required|integer|min:0',
            'requires_attachment' => 'boolean',
            'is_paid' => 'boolean',
        ]);

        return $this->success('Leave type created.', LeaveType::create($data), 201);
    }

    public function update(Request $request, LeaveType $leaveType)
    {
        $leaveType->update($request->validate([
            'name' => 'sometimes|string|max:100',
            'default_days_per_year' => 'sometimes|integer|min:0',
            'requires_attachment' => 'boolean',
            'is_paid' => 'boolean',
            'is_active' => 'boolean',
        ]));

        return $this->success('Leave type updated.', $leaveType);
    }

    /**
     * GET /api/leaves/balance
     */
    public function myBalance(Request $request)
    {
        $balances = LeaveBalance::with('leaveType')
            ->where('user_id', $request->user()->id)
            ->where('year', now()->year)
            ->get()
            ->map(fn ($b) => [
                'leave_type' => $b->leaveType->name,
                'allocated_days' => $b->allocated_days,
                'used_days' => $b->used_days,
                'carried_forward_days' => $b->carried_forward_days,
                'remaining_days' => $b->remaining_days,
            ]);

        return $this->success('OK', $balances);
    }
}
