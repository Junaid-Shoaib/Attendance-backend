# Attendance Management System — Backend (Laravel 12)

Secure, GPS-enabled REST API powering the Attendance Management mobile app.
Built with Laravel 12, Sanctum token auth, MySQL, and role-based access
control (Super Admin / Manager / Employee).

## Features implemented

- Token authentication (Sanctum) with per-device binding & approval workflow
- Role & permission management (super_admin, manager, employee)
- Employee/User management (create, edit, disable, assign manager/department)
- Attendance module: Time In/Out with mandatory GPS, geofencing (haversine
  distance to nearest office), device-time drift validation, mock-location
  detection, rooted/jailbroken device blocking, optional selfie capture, and
  a full audit trail (IP, device, coordinates) on every mark
- Leave management: leave types, balances, apply/cancel, two-stage approval
  workflow (Manager → optional Admin), automatic balance deduction
- Missed attendance regularization requests with approval workflow
- Attendance history, monthly/weekly views, and calendar endpoint
- Profile management (avatar, contact info, password, devices)
- Push notification token registration + FCM sending service, plus in-app
  database notifications (leave status, new requests, reminders)
- Admin & Employee & Manager dashboards, attendance/leave reports
- Full audit logging (audit_logs table) for login, time in/out, password
  changes, etc.

## Requirements

- PHP 8.2+
- Composer 2.x
- MySQL 8.0+ (or MariaDB 10.6+)
- Redis (optional, recommended for queue/cache in production)

## Setup

```bash
# 1. Install dependencies
composer install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Configure your database in .env
#    DB_DATABASE=attendance_management
#    DB_USERNAME=root
#    DB_PASSWORD=...

# 4. Run migrations + seed default data (departments, leave types, demo users)
php artisan migrate --seed

# 5. Link storage (for avatars/selfies/attachments)
php artisan storage:link

# 6. Serve
php artisan serve
# API base URL: http://localhost:8000/api/v1
```

Alternatively, import `../docs/schema.sql` directly into MySQL if you prefer
not to run migrations (it mirrors the migrations 1:1 and includes seed data).

## Demo accounts (from `UserSeeder`)

| Role        | Email                     | Password       |
|-------------|---------------------------|----------------|
| Super Admin | admin@attendance.app      | Password@123   |
| Manager     | manager@attendance.app    | Password@123   |
| Employee    | employee@attendance.app   | Password@123   |

**Change these before deploying to production.**

## Key configuration (`config/attendance.php` / `.env`)

| Setting | Purpose |
|---|---|
| `ATTENDANCE_DEFAULT_GEOFENCE_RADIUS_METERS` | Default allowed radius around an office (meters) |
| `ATTENDANCE_ALLOW_MOCK_LOCATION` | If `false`, rejects attendance when client reports a mock/fake GPS provider |
| `ATTENDANCE_BLOCK_ROOTED_DEVICES` | If `true`, blocks attendance from rooted/jailbroken devices |
| `ATTENDANCE_REQUIRE_SELFIE` | If `true`, selfie capture becomes mandatory on Time In/Out |
| `ATTENDANCE_DEVICE_BINDING_ENABLED` | If `true`, a new device requires admin approval before it can be used |
| `ATTENDANCE_MAX_TIME_DRIFT_MINUTES` | Max allowed drift between device clock and server clock |

Office locations used for geofencing are managed via `/api/v1/admin/offices`
(supports multiple branches/client sites, each with its own radius).

## Queue & scheduler (reminders, push notifications)

```bash
php artisan queue:work        # process notification jobs
php artisan schedule:work     # runs Time In / Time Out reminder jobs (see routes/console.php)
```

## Running tests

```bash
php artisan test
```

## Project structure

```
app/
  Http/Controllers/Api/   REST controllers (Auth, User, Attendance, Leave, ...)
  Http/Middleware/        RoleMiddleware, EnsureUserIsActive
  Http/Requests/          Form request validation classes
  Models/                 Eloquent models
  Services/               Business logic (GeofenceService, AttendanceService,
                           AttendanceSecurityService, LeaveService,
                           RegularizationService, PushNotificationService)
  Notifications/          Database/mail/push notification classes
database/
  migrations/              Full schema (19 migrations)
  seeders/                 Departments, designations, offices, leave types, demo users
routes/api.php             All API routes, grouped & role-protected
```

## API documentation

- OpenAPI spec: `../docs/openapi.yaml`
- Postman collection: `../docs/postman_collection.json`
- SQL schema (standalone): `../docs/schema.sql`

## Security notes

- All attendance-marking endpoints run `AttendanceSecurityService::validate()`
  which enforces, in order: GPS presence → mock-location rejection →
  root/jailbreak rejection → device-time drift check → device binding/approval
  → optional selfie requirement → office geofence containment.
- Sanctum tokens are scoped per device (`mobile:{device_id}`) and can be
  revoked individually by an admin via `PATCH /admin/devices/{device}/revoke`.
- Disabling a user (`PATCH /admin/users/{user}/disable`) immediately revokes
  all of their active tokens.
