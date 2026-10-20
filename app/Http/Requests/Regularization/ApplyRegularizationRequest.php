<?php

namespace App\Http\Requests\Regularization;

use Illuminate\Foundation\Http\FormRequest;

class ApplyRegularizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attendance_date' => ['required', 'date', 'before_or_equal:today'],
            'requested_time_in' => ['nullable', 'date'],
            'requested_time_out' => ['nullable', 'date', 'after:requested_time_in'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
