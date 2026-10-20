<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id ?? $this->route('user');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,'.$userId],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['sometimes', 'in:super_admin,manager,employee'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'designation_id' => ['nullable', 'exists:designations,id'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'office_id' => ['nullable', 'exists:offices,id'],
            'joining_date' => ['nullable', 'date'],
        ];
    }
}
