<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Leave\ApplyLeaveRequest;
use App\Http\Requests\Leave\ReviewLeaveRequest;
use App\Models\Leave;
use App\Notifications\LeaveStatusNotification;
use App\Notifications\NewLeaveRequestNotification;
use App\Services\LeaveService;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function __construct(private LeaveService $leaveService)
    {
    }

    /**
     * POST /api/leaves - Employee applies for leave.
     */
    public function store(ApplyLeaveRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('attachments', 'public');
        }

        $leave = $this->leaveService->apply($request->user(), $data);

        if ($leave->manager) {
            $leave->manager->notify(new NewLeaveRequestNotification($leave));
        }

        return $this->success('Leave request submitted successfully.', $leave->load('leaveType'), 201);
    }

    /**
     * GET /api/leaves - Employee: own leave history.
     */
    public function index(Request $request)
    {
        $query = Leave::with('leaveType')->where('user_id', $request->user()->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->success('OK', $query->latest()->paginate($request->get('per_page', 20)));
    }

    public function show(Request $request, Leave $leave)
    {
        $this->authorizeOwnerOrReviewer($request, $leave);

        return $this->success('OK', $leave->load(['leaveType', 'user', 'manager', 'admin']));
    }

    /**
     * PATCH /api/leaves/{leave}/cancel - Employee cancels own pending request.
     */
    public function cancel(Request $request, Leave $leave)
    {
        abort_unless($leave->user_id === $request->user()->id, 403);

        return $this->success('Leave request cancelled.', $this->leaveService->cancel($leave));
    }

    /**
     * GET /api/manager/leaves - Manager: team leave requests.
     */
    public function teamLeaves(Request $request)
    {
        $teamIds = $request->user()->subordinates()->pluck('id');

        $query = Leave::with(['leaveType', 'user'])->whereIn('user_id', $teamIds);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->success('OK', $query->latest()->paginate($request->get('per_page', 20)));
    }

    /**
     * PATCH /api/manager/leaves/{leave}/review - Manager approve/reject.
     */
    public function managerReview(ReviewLeaveRequest $request, Leave $leave)
    {
        abort_unless($leave->manager_id === $request->user()->id, 403, 'You are not the assigned manager for this request.');

        $leave = $this->leaveService->managerAction(
            $leave,
            $request->user(),
            $request->action,
            $request->remarks,
            $request->only(['start_date', 'end_date'])
        );
        $leave->user->notify(new LeaveStatusNotification($leave));

        return $this->success('Leave request '.$request->action.'d.', $leave);
    }

    /**
     * GET /api/admin/leaves - Admin: all leave requests (optionally pending admin approval).
     */
    public function adminIndex(Request $request)
    {
        $query = Leave::with(['leaveType', 'user', 'manager']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->success('OK', $query->latest()->paginate($request->get('per_page', 20)));
    }

    /**
     * PATCH /api/admin/leaves/{leave}/review - Admin final approval (optional second stage).
     */
    public function adminReview(ReviewLeaveRequest $request, Leave $leave)
    {
        $leave = $this->leaveService->adminAction(
            $leave,
            $request->user(),
            $request->action,
            $request->remarks,
            $request->only(['start_date', 'end_date'])
        );
        $leave->user->notify(new LeaveStatusNotification($leave));

        return $this->success('Leave request '.$request->action.'d.', $leave);
    }

    protected function authorizeOwnerOrReviewer(Request $request, Leave $leave): void
    {
        $user = $request->user();
        abort_unless(
            $leave->user_id === $user->id || $leave->manager_id === $user->id || $user->isSuperAdmin(),
            403
        );
    }
}
