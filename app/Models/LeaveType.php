<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $fillable = [
        'name', 'code', 'default_days_per_year', 'requires_attachment', 'is_paid', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_attachment' => 'boolean',
            'is_paid' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
