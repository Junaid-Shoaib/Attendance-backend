<?php

namespace App\Http\Controllers\Api;

use App\Exports\AttendanceReportExport;
use App\Exports\LeaveReportExport;
use App\Models\Attendance;
use App\Models\Leave;
use App\Models\LeaveBalance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class DashboardController extends Controller
{
    /**
     * GET /api/admin/dashboard
     */
    public function admin(Request $request)
    {
        $today = Carbon::today();

        $totalEmployees = User::where('role', '!=', 'super_admin')->where('status', 'active')->count();
    
        $presentToday = Attendance::whereDate('date', $today)->whereIn('status', ['present', 'late'])->count();
        $onLeaveToday = Leave::where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->count();
        $lateToday = Attendance::whereDate('date', $today)->where('status', 'late')->count();
        $absentToday = max($totalEmployees - $presentToday - $onLeaveToday, 0);

        $monthlyAttendance = Attendance::whereMonth('date', $today->month)
            ->whereYear('date', $today->year)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return $this->success('OK', [
            'total_employees' => $totalEmployees,
            'present_today' => $presentToday,
            'absent_today' => $absentToday,
            'on_leave_today' => $onLeaveToday,
            'late_today' => $lateToday,
            'attendance_percentage' => $totalEmployees > 0 ? round(($presentToday / $totalEmployees) * 100, 1) : 0,
            'monthly_attendance_breakdown' => $monthlyAttendance,
            'pending_leave_requests' => Leave::whereIn('status', ['pending', 'manager_approved'])->count(),
            'pending_device_approvals' => \App\Models\Device::where('is_approved', false)->count(),
        ]);
    }

    /**
     * GET /api/employee/dashboard
     */
    public function employee(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today();

        $todayAttendance = Attendance::where('user_id', $user->id)->whereDate('date', $today)->first();

        $balances = LeaveBalance::with('leaveType')
            ->where('user_id', $user->id)
            ->where('year', now()->year)
            ->get()
            ->map(fn ($b) => [
                'leave_type' => $b->leaveType->name,
                'remaining_days' => $b->remaining_days,
            ]);

        $monthAttendance = Attendance::where('user_id', $user->id)
            ->whereMonth('date', $today->month)
            ->whereYear('date', $today->year)
            ->get();

        return $this->success('OK', [
            'today_status' => $todayAttendance?->status ?? 'not_marked',
            'time_in' => $todayAttendance?->time_in,
            'time_out' => $todayAttendance?->time_out,
            'working_hours_today' => $todayAttendance?->working_hours,
            'leave_balance' => $balances,
            'pending_leave_requests' => Leave::where('user_id', $user->id)->whereIn('status', ['pending', 'manager_approved'])->count(),
            'monthly_present_days' => $monthAttendance->whereIn('status', ['present', 'late'])->count(),
            'monthly_absent_days' => $monthAttendance->where('status', 'absent')->count(),
            'monthly_late_days' => $monthAttendance->where('status', 'late')->count(),
        ]);
    }

    /**
     * GET /api/manager/dashboard
     */
    public function manager(Request $request)
    {
        $user = $request->user();
        // Team Size must reflect only Employee-role users belonging to this
        // manager (not other managers who may also report up to them), so
        // it stays accurate the moment Admin creates/reassigns/deletes an
        // Employee - this is a live query, never cached.
        $teamIds = User::where('manager_id', $user->id)
            ->where('role', 'employee')
            ->pluck('id');
        $today = Carbon::today();

        $presentToday = Attendance::whereIn('user_id', $teamIds)->whereDate('date', $today)->whereIn('status', ['present', 'late'])->count();

        return $this->success('OK', [
            'team_size' => $teamIds->count(),
            'present_today' => $presentToday,
            'pending_leave_approvals' => Leave::whereIn('user_id', $teamIds)->where('status', 'pending')->count(),
            'pending_regularizations' => \App\Models\Regularization::whereIn('user_id', $teamIds)->where('status', 'pending')->count(),
        ]);
    }

    /**
     * GET /api/admin/reports/attendance?from=&to=&department_id=&employee_id=&employee_ids[]=&export=csv|xlsx
     * Supports individual employee, multiple employees, and all-employees
     * modes. When "export" is present, streams a file download instead of
     * a JSON page (server-side filtered, so it stays performant for large
     * employee counts - no client-side filtering of an unbounded dataset).
     */
    public function attendanceReport(Request $request)
    {
        $query = $this->buildAttendanceReportQuery($request);

        if ($request->filled('export')) {
            return $this->exportAttendance($query, $request->get('export'));
        }

        return $this->success('OK', $query->orderByDesc('date')->paginate($request->get('per_page', 50)));
    }

    /**
     * GET /api/manager/reports/attendance
     * Same filters as the admin report, but the query is force-scoped to
     * Employee-role users only - enforced here on the backend so a Manager
     * cannot access Admin/Manager attendance by editing request params.
     */
    public function managerAttendanceReport(Request $request)
    {
        $query = $this->buildAttendanceReportQuery($request, employeeRoleOnly: true);

        if ($request->filled('export')) {
            return $this->exportAttendance($query, $request->get('export'));
        }

        return $this->success('OK', $query->orderByDesc('date')->paginate($request->get('per_page', 50)));
    }

    /**
     * GET /api/admin/reports/leaves?from=&to=&employee_id=&employee_ids[]=&export=csv|xlsx
     */
    public function leaveReport(Request $request)
    {
        $query = $this->buildLeaveReportQuery($request);

        if ($request->filled('export')) {
            return $this->exportLeaves($query, $request->get('export'));
        }

        return $this->success('OK', $query->orderByDesc('start_date')->paginate($request->get('per_page', 50)));
    }

    /**
     * GET /api/manager/reports/leaves
     * Backend-enforced Employee-role scoping, same as managerAttendanceReport.
     */
    public function managerLeaveReport(Request $request)
    {
        $query = $this->buildLeaveReportQuery($request, employeeRoleOnly: true);

        if ($request->filled('export')) {
            return $this->exportLeaves($query, $request->get('export'));
        }

        return $this->success('OK', $query->orderByDesc('start_date')->paginate($request->get('per_page', 50)));
    }

    /**
     * Shared attendance report query builder.
     *
     * Filters:
     *  - employee_id: single employee
     *  - employee_ids[]: multiple selected employees
     *  - (neither present): all employees (respecting employeeRoleOnly scope)
     *  - department_id, from, to: existing filters
     */
    private function buildAttendanceReportQuery(Request $request, bool $employeeRoleOnly = false)
    {
        $query = Attendance::with('user:id,name,employee_id,department_id,role')
            ->when($request->from, fn ($q) => $q->whereDate('date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('date', '<=', $request->to))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->department_id, fn ($q) => $q->whereHas('user', fn ($u) => $u->where('department_id', $request->department_id)));

        if ($request->filled('employee_id')) {
            // Individual employee
            $query->where('user_id', $request->employee_id);
        } elseif ($request->filled('employee_ids') && is_array($request->employee_ids)) {
            // Multiple selected employees
            $query->whereIn('user_id', $request->employee_ids);
        }
        // else: All Employees - no user_id filter.

        if ($employeeRoleOnly) {
            $query->whereHas('user', fn ($u) => $u->where('role', 'employee'));
        }

        return $query;
    }

    private function buildLeaveReportQuery(Request $request, bool $employeeRoleOnly = false)
    {
        $query = Leave::with(['user:id,name,employee_id,role', 'leaveType'])
            ->when($request->from, fn ($q) => $q->whereDate('start_date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('end_date', '<=', $request->to))
            ->when($request->status, fn ($q) => $q->where('status', $request->status));

        if ($request->filled('employee_id')) {
            $query->where('user_id', $request->employee_id);
        } elseif ($request->filled('employee_ids') && is_array($request->employee_ids)) {
            $query->whereIn('user_id', $request->employee_ids);
        }

        if ($employeeRoleOnly) {
            $query->whereHas('user', fn ($u) => $u->where('role', 'employee'));
        }

        return $query;
    }

    private function exportAttendance($query, string $format)
    {
        $rows = $query->orderByDesc('date')->get();
        $filename = 'attendance-report-'.now()->format('Ymd-His').'.'.($format === 'csv' ? 'csv' : 'xlsx');

        return Excel::download(new AttendanceReportExport($rows), $filename);
    }

    private function exportLeaves($query, string $format)
    {
        $rows = $query->orderByDesc('start_date')->get();
        $filename = 'leave-report-'.now()->format('Ymd-His').'.'.($format === 'csv' ? 'csv' : 'xlsx');

        return Excel::download(new LeaveReportExport($rows), $filename);
    }
}
