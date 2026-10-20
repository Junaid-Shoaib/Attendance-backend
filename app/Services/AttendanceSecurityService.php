<?php

namespace App\Services;

use App\Models\Device;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AttendanceSecurityService
{
    public function __construct(private GeofenceService $geofence)
    {
    }

    /**
     * Run every mandatory security check before allowing an attendance
     * mark (time in or time out). Throws ValidationException on failure.
     *
     * @return array{office: ?\App\Models\Office, distance: ?float, device: Device}
     */
    public function validate(User $user, array $payload): array
    {
        $config = config('attendance.security');

        // 1. GPS coordinates must be present (GPS enabled + permission granted on client)
        if (empty($payload['latitude']) || empty($payload['longitude'])) {
            throw ValidationException::withMessages([
                'location' => 'GPS location is required. Please enable location services and grant permission.',
            ]);
        }

        // 2. Mock / fake GPS detection (client reports this flag from OS-level checks)
        if (! empty($payload['is_mock_location']) && ! $config['allow_mock_location']) {
            throw ValidationException::withMessages([
                'location' => 'Fake/mock GPS location detected. Attendance cannot be marked using a spoofed location.',
            ]);
        }

        // 3. Root / jailbreak detection
        if (! empty($payload['is_rooted_or_jailbroken']) && $config['block_rooted_devices']) {
            throw ValidationException::withMessages([
                'device' => 'Attendance cannot be marked from a rooted or jailbroken device.',
            ]);
        }

        // 4. Device time validation - reject if client clock drifts too far from server time
        if (! empty($payload['device_timestamp'])) {
            $deviceTime = Carbon::parse($payload['device_timestamp']);
            $drift = abs(now()->diffInMinutes($deviceTime, false));

            if ($drift > $config['max_time_drift_minutes']) {
                throw ValidationException::withMessages([
                    'device_time' => 'Device time does not match server time. Please correct your device clock and try again.',
                ]);
            }
        }

        // 5. Device binding - device must be registered & approved for this user
        $device = $this->resolveDevice($user, $payload);

        // 6. Selfie verification (optional, controlled by company setting)
        if ($config['require_selfie'] && empty($payload['selfie'])) {
            throw ValidationException::withMessages([
                'selfie' => 'A live selfie is required to mark attendance.',
            ]);
        }

        // 7. Office geofencing
        $geofenceResult = ['within' => true, 'office' => null, 'distance' => null];

        if (config('attendance.geofence.enforce')) {
            $geofenceResult = $this->geofence->resolveNearestOffice($payload['latitude'], $payload['longitude']);

            if (! $geofenceResult['within']) {
                throw ValidationException::withMessages([
                    'location' => 'You are outside the approved office/client location radius. Attendance was not marked.',
                ]);
            }
        }

        return [
            'office' => $geofenceResult['office'],
            'distance' => $geofenceResult['distance'],
            'device' => $device,
        ];
    }

    protected function resolveDevice(User $user, array $payload): Device
    {
        $deviceId = $payload['device_id'] ?? null;

        if (! $deviceId) {
            throw ValidationException::withMessages([
                'device' => 'Device identifier is missing from the request.',
            ]);
        }

        $device = Device::where('user_id', $user->id)->where('device_id', $deviceId)->first();

        if (! $device) {
            // First time this device is seen for this user -> register it, unapproved.
            $device = Device::create([
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'device_name' => $payload['device_name'] ?? null,
                'platform' => $payload['platform'] ?? null,
                'os_version' => $payload['os_version'] ?? null,
                'app_version' => $payload['app_version'] ?? null,
                'is_rooted_or_jailbroken' => (bool) ($payload['is_rooted_or_jailbroken'] ?? false),
                'is_primary' => ! Device::where('user_id', $user->id)->exists(),
                // If device binding isn't enforced, auto-approve. Otherwise require admin approval,
                // except the very first device a user ever registers.
                'is_approved' => ! config('attendance.security.device_binding_enabled')
                    || ! Device::where('user_id', $user->id)->exists(),
                'approved_at' => now(),
            ]);
        }

        if (config('attendance.security.device_binding_enabled') && ! $device->is_approved) {
            throw ValidationException::withMessages([
                'device' => 'This device is not approved for your account. Please contact your administrator to approve this device, or log in from your registered device.',
            ]);
        }

        $device->update(['last_used_at' => now()]);

        return $device;
    }
}
