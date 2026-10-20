<?php

// Custom application config for attendance security & business rules.
return [
    'geofence' => [
        'default_radius_meters' => env('ATTENDANCE_DEFAULT_GEOFENCE_RADIUS_METERS', 200),
        'enforce' => true,
    ],

    'security' => [
        'allow_mock_location' => env('ATTENDANCE_ALLOW_MOCK_LOCATION', false),
        'block_rooted_devices' => env('ATTENDANCE_BLOCK_ROOTED_DEVICES', true),
        'require_selfie' => env('ATTENDANCE_REQUIRE_SELFIE', false),
        'device_binding_enabled' => env('ATTENDANCE_DEVICE_BINDING_ENABLED', true),
        'max_time_drift_minutes' => env('ATTENDANCE_MAX_TIME_DRIFT_MINUTES', 5),
    ],

    'working_hours' => [
        'standard_hours_per_day' => 8,
        'late_grace_minutes' => 15,
        'half_day_threshold_hours' => 4,
    ],

    'leave' => [
        'default_annual_days' => 14,
        'default_sick_days' => 10,
        'default_casual_days' => 7,
    ],
];
