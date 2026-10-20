<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class TimeInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'address' => ['nullable', 'string', 'max:255'],
            'device_id' => ['required', 'string'],
            'device_name' => ['nullable', 'string'],
            'platform' => ['nullable', 'in:ios,android'],
            'os_version' => ['nullable', 'string'],
            'app_version' => ['nullable', 'string'],
            'is_rooted_or_jailbroken' => ['nullable', 'boolean'],
            'is_mock_location' => ['nullable', 'boolean'],
            'device_timestamp' => ['nullable', 'date'],
            'selfie' => ['nullable', 'image', 'max:5120'],
        ];
    }
}
