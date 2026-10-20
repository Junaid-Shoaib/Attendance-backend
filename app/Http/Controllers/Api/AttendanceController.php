<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Attendance\TimeInRequest;
use App\Http\Requests\Attendance\TimeOutRequest;
use App\Models\AuditLog;
use App\Models\Attendance;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendanceService)
    {
    }

    /**
     * POST /api/attendance/time-in
     * Runs all mandatory checks (GPS, internet implied by request, permission,
     * device time, auth, geofence, device binding, mock-location & root
     * detection) before persisting the Time In record.
     */
    public function timeIn(TimeInRequest $request)
    {
        $payload = $request->validated();

        if ($request->hasFile('selfie')) {
            $payload['selfie_path'] = $request->file('selfie')->store('selfies', 'public');
        }

        $attendance = $this->attendanceService->timeIn($request->user(), $payload, $request->ip());

        AuditLog::record($request->user()->id, 'time_in', $request->ip(), $payload, ['attendance_id' => $attendance->id]);

        return $this->success('Time In marked successfully.', $attendance);
    }

    /**
     * POST /api/attendance/time-out
     */
    public function timeOut(TimeOutRequest $request)
    {
        $payload = $request->validated();

        if ($request->hasFile('selfie')) {
            $payload['selfie_path'] = $request->file('selfie')->store('selfies', 'public');
        }

        $attendance = $this->attendanceService->timeOut($request->user(), $payload, $request->ip());

        AuditLog::record($request->user()->id, 'time_out', $request->ip(), $payload, ['attendance_id' => $attendance->id]);

        return $this->success('Time Out marked successfully.', $attendance);
    }

    /**
     * GET /api/attendance/today
     */
    public function today(Request $request)
    {
        $attendance = Attendance::where('user_id', $request->user()->id)
            ->whereDate('date', Carbon::today())
            ->first();

        return $this->success('OK', $attendance);
    }

    /**
     * GET /api/attendance/history?from=&to=&status=
     */
    public function history(Request $request)
    {
        $query = Attendance::where('user_id', $request->user()->id);

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->to);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->success('OK', $query->orderByDesc('date')->paginate($request->get('per_page', 30)));
    }

    /**
     * GET /api/attendance/calendar?month=7&year=2026
     * Returns day-by-day status for the interactive calendar view
     * (present / absent / leave / holiday / late color indicators).
     */
    public function calendar(Request $request)
    {
        $month = (int) $request->get('month', now()->month);
        $year = (int) $request->get('year', now()->year);

        $records = Attendance::where('user_id', $request->user()->id)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->get()
            ->keyBy(fn ($a) => $a->date->format('Y-m-d'));

        return $this->success('OK', $records);
    }

    /**
     * GET /api/manager/team-attendance?date=
     */
    public function teamAttendance(Request $request)
    {
        $date = $request->get('date', Carbon::today()->toDateString());
        $teamIds = $request->user()->subordinates()->pluck('id');

        $attendance = Attendance::with('user:id,name,employee_id')
            ->whereIn('user_id', $teamIds)
            ->whereDate('date', $date)
            ->get();

        return $this->success('OK', $attendance);
    }

    /**
     * GET /api/admin/attendance?date=
     * Company-wide attendance for a single day (all employees, not just
     * the requesting admin's own direct reports).
     */
    public function companyAttendance(Request $request)
    {
        $date = $request->get('date', Carbon::today()->toDateString());

        $attendance = Attendance::with('user:id,name,employee_id,department_id')
            ->whereDate('date', $date)
            ->get();

        return $this->success('OK', $attendance);
    }
}
