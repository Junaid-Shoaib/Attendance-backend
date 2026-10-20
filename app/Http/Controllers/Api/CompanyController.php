<?php

namespace App\Http\Controllers\Api;

use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Holiday;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CompanyController extends Controller
{
    // =================================================================
    // Attendance Working Hours Settings (Section 1)
    // =================================================================

    /**
     * GET /api/admin/settings/attendance
     * Returns the currently configured full-time working hours and the
     * half-day hour threshold. Falls back to config/attendance.php
     * defaults the first time this is called (before Admin ever saves).
     */
    public function attendanceSettings()
    {
        return $this->success('OK', [
            'full_time_start' => CompanySetting::get('attendance.full_time_start', '09:00'),
            'full_time_end' => CompanySetting::get('attendance.full_time_end', '18:00'),
            'half_day_hours' => (float) CompanySetting::get(
                'attendance.half_day_hours',
                config('attendance.working_hours.half_day_threshold_hours', 4)
            ),
            'late_grace_minutes' => (int) CompanySetting::get(
                'attendance.late_grace_minutes',
                config('attendance.working_hours.late_grace_minutes', 15)
            ),
        ]);
    }

    /**
     * PUT /api/admin/settings/attendance
     * Persists working-hours configuration so it drives attendance
     * calculations (late/half-day) without any code changes.
     */
    public function updateAttendanceSettings(Request $request)
    {
        $data = $request->validate([
            'full_time_start' => ['required', 'date_format:H:i'],
            'full_time_end' => ['required', 'date_format:H:i'],
            'half_day_hours' => ['required', 'numeric', 'min:1', 'max:23'],
            'late_grace_minutes' => ['nullable', 'integer', 'min:0', 'max:180'],
        ]);

        if ($data['full_time_end'] <= $data['full_time_start']) {
            throw ValidationException::withMessages([
                'full_time_end' => 'Full Time End Time must be after Full Time Start Time.',
            ]);
        }

        CompanySetting::set('attendance.full_time_start', $data['full_time_start']);
        CompanySetting::set('attendance.full_time_end', $data['full_time_end']);
        CompanySetting::set('attendance.half_day_hours', $data['half_day_hours']);
        CompanySetting::set('attendance.late_grace_minutes', $data['late_grace_minutes'] ?? 15);

        return $this->success('Attendance working hours updated successfully.', [
            'full_time_start' => $data['full_time_start'],
            'full_time_end' => $data['full_time_end'],
            'half_day_hours' => (float) $data['half_day_hours'],
            'late_grace_minutes' => (int) ($data['late_grace_minutes'] ?? 15),
        ]);
    }

    // =================================================================
    // Departments
    // =================================================================
    public function departments(Request $request)
    {
        $query = $request->boolean('all') ? Department::query() : Department::where('is_active', true);

        return $this->success('OK', $query->orderBy('name')->get());
    }

    public function storeDepartment(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|unique:departments,code',
            'is_active' => 'boolean',
        ]);

        return $this->success('Department created.', Department::create($data), 201);
    }

    public function updateDepartment(Request $request, Department $department)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => ['nullable', 'string', Rule::unique('departments', 'code')->ignore($department->id)],
            'is_active' => 'boolean',
        ]);

        $department->update($data);

        return $this->success('Department updated.', $department);
    }

    public function destroyDepartment(Department $department)
    {
        // Preserve referential integrity: employees keep their historical
        // record (department_id is nullOnDelete at the DB level), but we
        // surface a clear message so Admin understands the effect.
        $employeeCount = $department->employees()->count();
        $department->delete();

        return $this->success(
            $employeeCount > 0
                ? "Department deleted. {$employeeCount} employee(s) previously assigned to it are now unassigned."
                : 'Department deleted.',
            null
        );
    }

    // =================================================================
    // Designations
    // =================================================================
    public function designations(Request $request)
    {
        $query = $request->boolean('all') ? Designation::query() : Designation::where('is_active', true);

        return $this->success('OK', $query->orderBy('title')->get());
    }

    public function storeDesignation(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        return $this->success('Designation created.', Designation::create($data), 201);
    }

    public function updateDesignation(Request $request, Designation $designation)
    {
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $designation->update($data);

        return $this->success('Designation updated.', $designation);
    }

    public function destroyDesignation(Designation $designation)
    {
        $employeeCount = $designation->employees()->count();
        $designation->delete();

        return $this->success(
            $employeeCount > 0
                ? "Designation deleted. {$employeeCount} employee(s) previously assigned to it are now unassigned."
                : 'Designation deleted.',
            null
        );
    }

    // =================================================================
    // Offices (for geofencing)
    // =================================================================
    public function offices(Request $request)
    {
        $query = $request->boolean('all') ? Office::query() : Office::where('is_active', true);

        return $this->success('OK', $query->orderBy('name')->get());
    }

    public function storeOffice(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_meters' => 'nullable|integer|min:10|max:5000',
        ]);

        return $this->success('Office location created.', Office::create($data), 201);
    }

    public function updateOffice(Request $request, Office $office)
    {
        // Geofencing fields (latitude/longitude/radius) are validated the
        // same way as on create so editing never leaves them in a bad
        // state; every existing field is preserved unless explicitly sent.
        $office->update($request->validate([
            'name' => 'sometimes|string|max:255',
            'address' => 'nullable|string',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'radius_meters' => 'sometimes|integer|min:10|max:5000',
            'is_active' => 'boolean',
        ]));

        return $this->success('Office location updated.', $office);
    }

    public function destroyOffice(Office $office)
    {
        $employeeCount = $office->employees()->count();
        $office->delete();

        return $this->success(
            $employeeCount > 0
                ? "Office deleted. {$employeeCount} employee(s) previously assigned to it are now unassigned."
                : 'Office deleted.',
            null
        );
    }

    // =================================================================
    // Holidays
    // =================================================================
    public function holidays()
    {
        return $this->success('OK', Holiday::orderBy('date')->get());
    }

    public function storeHoliday(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'is_recurring_yearly' => 'boolean',
        ]);

        return $this->success('Holiday created.', Holiday::create($data), 201);
    }

    public function updateHoliday(Request $request, Holiday $holiday)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'date' => 'sometimes|required|date',
            'is_recurring_yearly' => 'boolean',
        ]);

        $holiday->update($data);

        return $this->success('Holiday updated.', $holiday);
    }

    public function destroyHoliday(Holiday $holiday)
    {
        $holiday->delete();

        return $this->success('Holiday deleted.', null);
    }
}
