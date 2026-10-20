<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_id' => ['required', 'string'],
            'device_name' => ['nullable', 'string'],
            'platform' => ['nullable', 'in:web,ios,android'],
            'os_version' => ['nullable', 'string'],
            'app_version' => ['nullable', 'string'],
            'is_rooted_or_jailbroken' => ['nullable', 'boolean'],
            'fcm_token' => ['nullable', 'string'],
        ];
    }
}
