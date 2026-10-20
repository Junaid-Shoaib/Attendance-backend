<?php

namespace App\Http\Controllers\Api;

use App\Models\Device;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    /**
     * GET /api/admin/devices?status=pending - Admin: list devices awaiting approval.
     */
    public function index(Request $request)
    {
        $query = Device::query()->with('user:id,name,employee_id,email');

        if ($request->get('status') === 'pending') {
            $query->where('is_approved', false);
        }

        return $this->success('OK', $query->latest()->paginate(20));
    }

    /**
     * PATCH /api/admin/devices/{device}/approve
     */
    public function approve(Request $request, Device $device)
    {
        $device->update([
            'is_approved' => true,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        return $this->success('Device approved successfully.', $device);
    }

    /**
     * PATCH /api/admin/devices/{device}/revoke
     */
    public function revoke(Device $device)
    {
        $device->update(['is_approved' => false, 'approved_at' => null, 'approved_by' => null]);
        $device->user->tokens()->delete();

        return $this->success('Device access revoked.', $device);
    }

    /**
     * GET /api/profile/devices - Employee: view own registered devices.
     */
    public function myDevices(Request $request)
    {
        return $this->success('OK', $request->user()->devices);
    }
}
