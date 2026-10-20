<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\FcmToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/auth/login
     * Employees do not self-register; accounts are provisioned by an admin.
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');
        if (! Auth::attempt($credentials)) {
            AuditLog::record(null, 'login_failed', $request->ip(), [], ['email' => $request->email]);

            throw ValidationException::withMessages([
                'email' => 'The provided credentials are incorrect.',
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => 'Your account has been disabled. Please contact your administrator.',
            ]);
        }

        // Device binding check at login time (in addition to attendance-time checks)
        $deviceId = $request->device_id;
        $device = Device::where('user_id', $user->id)->where('device_id', $deviceId)->first();

        if (! $device) {
            $device = Device::create([
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'device_name' => $request->device_name,
                'platform' => $request->platform,
                'os_version' => $request->os_version,
                'app_version' => $request->app_version,
                'is_rooted_or_jailbroken' => (bool) $request->is_rooted_or_jailbroken,
                'is_primary' => ! Device::where('user_id', $user->id)->exists(),
                'is_approved' => ! config('attendance.security.device_binding_enabled')
                    || ! Device::where('user_id', $user->id)->exists(),
                'approved_at' => now(),
            ]);
        } else {
            $device->update([
                'last_used_at' => now(),
                'app_version' => $request->app_version ?? $device->app_version,
            ]);
        }
        if (config('attendance.security.device_binding_enabled') && !$device->is_approved) {
            return $this->error(
                'This device is pending administrator approval. You will be notified once approved.',
                403
            );
        }

        if ($request->fcm_token) {
            FcmToken::updateOrCreate(
                ['token' => $request->fcm_token],
                ['user_id' => $user->id, 'device_id' => $deviceId, 'platform' => $request->platform]
            );
        }

        $token = $user->createToken('mobile:'.$deviceId, ['*'], now()->addMinutes(
            (int) config('sanctum.expiration', 43200)
        ))->plainTextToken;

        AuditLog::record($user->id, 'login', $request->ip(), $request->only('platform', 'os_version', 'app_version'));

        return $this->success('Logged in successfully.', [
            'user' => $user->load(['department', 'designation', 'manager', 'office']),
            'token' => $token,
            'device_approved' => $device->is_approved,
        ]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        AuditLog::record($request->user()->id, 'logout', $request->ip());

        return $this->success('Logged out successfully.');
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request)
    {
        return $this->success('OK', $request->user()->load(['department', 'designation', 'manager', 'office']));
    }

    /**
     * POST /api/auth/change-password
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required'],
            'new_password' => ['required', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Current password is incorrect.']);
        }

        $user->update(['password' => Hash::make($request->new_password)]);
        AuditLog::record($user->id, 'password_changed', $request->ip());

        return $this->success('Password changed successfully.');
    }

    /**
     * POST /api/auth/forgot-password
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? $this->success('Password reset link sent to your email.')
            : $this->error('Unable to send reset link.', 422);
    }

    /**
     * POST /api/auth/reset-password
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->update(['password' => Hash::make($password)]);
                $user->tokens()->delete();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? $this->success('Password has been reset successfully.')
            : $this->error('Unable to reset password. The link may be invalid or expired.', 422);
    }
}
