<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * GET /api/profile
     */
    public function show(Request $request)
    {
        return $this->success('OK', $request->user()->load(['department', 'designation', 'manager', 'office']));
    }

    /**
     * PUT /api/profile
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
        ]);

        $user->update($data);

        return $this->success('Profile updated successfully.', $user);
    }

    /**
     * POST /api/profile/avatar
     */
    public function updateAvatar(Request $request)
    {
        $request->validate(['avatar' => 'required|image|max:5120']);

        $user = $request->user();
        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['profile_picture' => $path]);

        return $this->success('Profile picture updated successfully.', $user);
    }

    /**
     * POST /api/profile/fcm-token - Register push notification token.
     */
    public function updateFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
            'device_id' => 'nullable|string',
            'platform' => 'nullable|in:ios,android',
        ]);

        \App\Models\FcmToken::updateOrCreate(
            ['token' => $request->fcm_token],
            [
                'user_id' => $request->user()->id,
                'device_id' => $request->device_id,
                'platform' => $request->platform,
            ]
        );

        return $this->success('Push notification token registered.');
    }
}
