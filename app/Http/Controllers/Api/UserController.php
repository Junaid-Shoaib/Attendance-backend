<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * GET /api/admin/users - Super Admin: list/search/filter employees.
     */
    public function index(Request $request)
    {
        $query = User::query()->with(['department', 'designation', 'manager']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->success('OK', $query->orderBy('name')->paginate($request->get('per_page', 20)));
    }

    /**
     * POST /api/admin/users - Create Employee (Admin).
     */
    public function store(StoreUserRequest $request)
    {
        $user = User::create([
            ...$request->validated(),
            'password' => Hash::make($request->password),
        ]);

        // Seed default leave balances for the current year based on active leave types.
        $year = now()->year;
        foreach (LeaveType::where('is_active', true)->get() as $type) {
            LeaveBalance::create([
                'user_id' => $user->id,
                'leave_type_id' => $type->id,
                'year' => $year,
                'allocated_days' => $type->default_days_per_year,
            ]);
        }

        return $this->success('Employee created successfully.', $user, 201);
    }

    /**
     * GET /api/admin/users/{user}
     */
    public function show(User $user)
    {
        return $this->success('OK', $user->load(['department', 'designation', 'manager', 'office', 'devices']));
    }

    /**
     * PUT /api/admin/users/{user} - Edit Employee.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $user->update($request->validated());

        return $this->success('Employee updated successfully.', $user);
    }

    /**
     * PATCH /api/admin/users/{user}/disable
     */
    public function disable(User $user)
    {
        $user->update(['status' => 'disabled']);
        $user->tokens()->delete();

        return $this->success('Employee disabled successfully.', $user);
    }

    /**
     * PATCH /api/admin/users/{user}/enable
     */
    public function enable(User $user)
    {
        $user->update(['status' => 'active']);

        return $this->success('Employee enabled successfully.', $user);
    }

    /**
     * PATCH /api/admin/users/{user}/assign-manager
     */
    public function assignManager(Request $request, User $user)
    {
        $request->validate(['manager_id' => 'required|exists:users,id']);
        $user->update(['manager_id' => $request->manager_id]);

        return $this->success('Manager assigned successfully.', $user);
    }

    /**
     * PATCH /api/admin/users/{user}/assign-department
     */
    public function assignDepartment(Request $request, User $user)
    {
        $request->validate([
            'department_id' => 'nullable|exists:departments,id',
            'designation_id' => 'nullable|exists:designations,id',
        ]);
        $user->update($request->only('department_id', 'designation_id'));

        return $this->success('Department/designation assigned successfully.', $user);
    }

    /**
     * GET /api/manager/team - Manager: list of assigned employees (direct
     * reports only). Restricted to the Employee role so a Manager never
     * sees another Manager/Admin who happens to report to them.
     */
    public function myTeam(Request $request)
    {
        $team = $request->user()->subordinates()
            ->where('role', 'employee')
            ->with(['department', 'designation'])
            ->get();

        return $this->success('OK', $team);
    }

    /**
     * GET /api/manager/employees - Manager: view/search ALL Employee-role
     * users company-wide (for verification - e.g. spotting that a
     * physically-present employee has no account yet). Read-only: no
     * create/edit/delete route is exposed to Manager for this resource.
     * Only role=employee records are ever returned here, enforced on the
     * backend so Admins/Managers/other privileged users are never exposed
     * regardless of any frontend filtering.
     */
    public function managerEmployeeDirectory(Request $request)
    {
        $query = User::query()
            ->where('role', 'employee')
            ->with(['department', 'designation']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->success('OK', $query->orderBy('name')->paginate($request->get('per_page', 20)));
    }

    /**
     * GET /api/manager/employees/{user} - Manager: view a single
     * Employee-role user's details. 404s (via role scoping, not a raw
     * lookup) if the target is not an Employee, so a Manager cannot probe
     * Admin/Manager records by guessing an id.
     */
    public function managerEmployeeShow(Request $request, User $user)
    {
        abort_unless($user->role === 'employee', 404);

        return $this->success('OK', $user->load(['department', 'designation', 'office']));
    }
}
