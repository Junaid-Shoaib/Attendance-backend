<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\LeaveTypeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RegularizationController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Attendance Management System
|--------------------------------------------------------------------------
| All routes are versioned under /api/v1 and return JSON. Authenticated
| routes use Laravel Sanctum personal access tokens (mobile-friendly).
*/

Route::prefix('v1')->group(function () {

    // ---------------------------------------------------------------
    // Public authentication routes
    // ---------------------------------------------------------------
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
    });

    // ---------------------------------------------------------------
    // Authenticated routes (any role)
    // ---------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'active'])->group(function () {

        Route::prefix('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::post('change-password', [AuthController::class, 'changePassword']);
        });

        // Profile
        Route::prefix('profile')->group(function () {
            Route::get('/', [ProfileController::class, 'show']);
            Route::put('/', [ProfileController::class, 'update']);
            Route::post('avatar', [ProfileController::class, 'updateAvatar']);
            Route::post('fcm-token', [ProfileController::class, 'updateFcmToken']);
            Route::get('devices', [DeviceController::class, 'myDevices']);
        });

        // Attendance (Employee)
        Route::prefix('attendance')->group(function () {
            Route::post('time-in', [AttendanceController::class, 'timeIn']);
            Route::post('time-out', [AttendanceController::class, 'timeOut']);
            Route::get('today', [AttendanceController::class, 'today']);
            Route::get('history', [AttendanceController::class, 'history']);
            Route::get('calendar', [AttendanceController::class, 'calendar']);
        });

        // Leave (Employee)
        Route::prefix('leaves')->group(function () {
            Route::get('/', [LeaveController::class, 'index']);
            Route::post('/', [LeaveController::class, 'store']);
            Route::get('balance', [LeaveTypeController::class, 'myBalance']);
            Route::get('types', [LeaveTypeController::class, 'index']);
            Route::get('{leave}', [LeaveController::class, 'show']);
            Route::patch('{leave}/cancel', [LeaveController::class, 'cancel']);
        });

        // Attendance Regularization (Employee)
        Route::prefix('regularizations')->group(function () {
            Route::get('/', [RegularizationController::class, 'index']);
            Route::post('/', [RegularizationController::class, 'store']);
        });

        // Notifications
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('unread-count', [NotificationController::class, 'unreadCount']);
            Route::patch('{id}/read', [NotificationController::class, 'markAsRead']);
            Route::patch('read-all', [NotificationController::class, 'markAllAsRead']);
        });

        // Employee dashboard
        Route::get('employee/dashboard', [DashboardController::class, 'employee']);

        // Reference data available to all authenticated users
        Route::get('departments', [CompanyController::class, 'departments']);
        Route::get('designations', [CompanyController::class, 'designations']);
        Route::get('offices', [CompanyController::class, 'offices']);
        Route::get('holidays', [CompanyController::class, 'holidays']);

        // -------------------------------------------------------
        // Manager-only routes
        // -------------------------------------------------------
        Route::middleware('role:manager')->prefix('manager')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'manager']);
            Route::get('team', [UserController::class, 'myTeam']);
            Route::get('team-attendance', [AttendanceController::class, 'teamAttendance']);
            Route::get('leaves', [LeaveController::class, 'teamLeaves']);
            Route::patch('leaves/{leave}/review', [LeaveController::class, 'managerReview']);
            Route::get('regularizations', [RegularizationController::class, 'pendingForReview']);
            Route::patch('regularizations/{regularization}/review', [RegularizationController::class, 'review']);

            // Employee directory (Section 5) - read-only, Employee-role only
            Route::get('employees', [UserController::class, 'managerEmployeeDirectory']);
            Route::get('employees/{user}', [UserController::class, 'managerEmployeeShow']);

            // Manager reports (Section 6) - backend-enforced Employee-role scoping
            Route::get('reports/attendance', [DashboardController::class, 'managerAttendanceReport']);
            Route::get('reports/leaves', [DashboardController::class, 'managerLeaveReport']);
        });

        // -------------------------------------------------------
        // Super Admin-only routes
        // -------------------------------------------------------
        Route::middleware('role:super_admin')->prefix('admin')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'admin']);

            // User management
            Route::apiResource('users', UserController::class)->except(['destroy']);
            Route::patch('users/{user}/disable', [UserController::class, 'disable']);
            Route::patch('users/{user}/enable', [UserController::class, 'enable']);
            Route::patch('users/{user}/assign-manager', [UserController::class, 'assignManager']);
            Route::patch('users/{user}/assign-department', [UserController::class, 'assignDepartment']);

            // Device management
            Route::get('devices', [DeviceController::class, 'index']);
            Route::patch('devices/{device}/approve', [DeviceController::class, 'approve']);
            Route::patch('devices/{device}/revoke', [DeviceController::class, 'revoke']);

            // Leave administration (final approval stage + oversight)
            Route::get('leaves', [LeaveController::class, 'adminIndex']);
            Route::patch('leaves/{leave}/review', [LeaveController::class, 'adminReview']);
            Route::post('leave-types', [LeaveTypeController::class, 'store']);
            Route::put('leave-types/{leaveType}', [LeaveTypeController::class, 'update']);

            // Regularization oversight (admin can also review any request)
            Route::get('regularizations', [RegularizationController::class, 'pendingForReview']);
            Route::patch('regularizations/{regularization}/review', [RegularizationController::class, 'review']);

            // Attendance oversight across all employees
            Route::get('attendance', [AttendanceController::class, 'companyAttendance']);

            // Company settings
            Route::post('departments', [CompanyController::class, 'storeDepartment']);
            Route::put('departments/{department}', [CompanyController::class, 'updateDepartment']);
            Route::delete('departments/{department}', [CompanyController::class, 'destroyDepartment']);

            Route::post('designations', [CompanyController::class, 'storeDesignation']);
            Route::put('designations/{designation}', [CompanyController::class, 'updateDesignation']);
            Route::delete('designations/{designation}', [CompanyController::class, 'destroyDesignation']);

            Route::post('offices', [CompanyController::class, 'storeOffice']);
            Route::put('offices/{office}', [CompanyController::class, 'updateOffice']);
            Route::delete('offices/{office}', [CompanyController::class, 'destroyOffice']);

            Route::post('holidays', [CompanyController::class, 'storeHoliday']);
            Route::put('holidays/{holiday}', [CompanyController::class, 'updateHoliday']);
            Route::delete('holidays/{holiday}', [CompanyController::class, 'destroyHoliday']);

            // Attendance working hours settings (Section 1)
            Route::get('settings/attendance', [CompanyController::class, 'attendanceSettings']);
            Route::put('settings/attendance', [CompanyController::class, 'updateAttendanceSettings']);

            // Reports
            Route::get('reports/attendance', [DashboardController::class, 'attendanceReport']);
            Route::get('reports/leaves', [DashboardController::class, 'leaveReport']);
        });
    });
});
