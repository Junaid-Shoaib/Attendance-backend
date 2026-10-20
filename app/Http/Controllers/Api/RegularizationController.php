<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Regularization\ApplyRegularizationRequest;
use App\Models\Regularization;
use App\Notifications\RegularizationStatusNotification;
use App\Services\RegularizationService;
use Illuminate\Http\Request;

class RegularizationController extends Controller
{
    public function __construct(private RegularizationService $service)
    {
    }

    /**
     * POST /api/regularizations - Employee submits a missed attendance correction.
     */
    public function store(ApplyRegularizationRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('attachments', 'public');
        }

        $regularization = $this->service->apply($request->user(), $data);

        return $this->success('Regularization request submitted.', $regularization, 201);
    }

    /**
     * GET /api/regularizations - Employee: own requests.
     */
    public function index(Request $request)
    {
        $query = Regularization::where('user_id', $request->user()->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->success('OK', $query->latest()->paginate($request->get('per_page', 20)));
    }

    /**
     * GET /api/manager/regularizations - Manager/Admin: pending requests for team.
     */
    public function pendingForReview(Request $request)
    {
        $user = $request->user();
        $query = Regularization::with('user:id,name,employee_id')->where('status', 'pending');

        if ($user->isManager()) {
            $teamIds = $user->subordinates()->pluck('id');
            $query->whereIn('user_id', $teamIds);
        }

        return $this->success('OK', $query->latest()->paginate($request->get('per_page', 20)));
    }

    /**
     * PATCH /api/regularizations/{regularization}/review
     */
    public function review(Request $request, Regularization $regularization)
    {
        // Manager/Admin may correct the employee-submitted date/time
        // before approving (e.g. employee typed 09:30 but the real
        // Time In was 09:00). These fields are optional and only used
        // when the action is 'approve'; a plain decline needs none of
        // them.
        $data = $request->validate([
            'action' => 'required|in:approve,reject',
            'remarks' => 'nullable|string|max:1000',
            'attendance_date' => 'nullable|date',
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i|after:time_in',
        ]);

        if ($request->user()->isManager()) {
            abort_unless(
                $regularization->user->manager_id === $request->user()->id,
                403,
                'You are not the assigned manager for this request.'
            );
        }

        $overrides = [];
        if (! empty($data['attendance_date'])) {
            $overrides['attendance_date'] = $data['attendance_date'];
        }
        $dateForTime = $overrides['attendance_date'] ?? $regularization->attendance_date->toDateString();
        if (! empty($data['time_in'])) {
            $overrides['requested_time_in'] = $dateForTime.' '.$data['time_in'].':00';
        }
        if (! empty($data['time_out'])) {
            $overrides['requested_time_out'] = $dateForTime.' '.$data['time_out'].':00';
        }

        $regularization = $this->service->review($regularization, $request->user(), $request->action, $request->remarks, $overrides);
        $regularization->user->notify(new RegularizationStatusNotification($regularization));

        return $this->success('Regularization request '.$request->action.'d.', $regularization);
    }
}
