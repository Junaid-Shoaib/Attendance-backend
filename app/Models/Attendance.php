<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = [
        'user_id', 'date',
        'time_in', 'time_in_latitude', 'time_in_longitude', 'time_in_address',
        'time_in_selfie', 'time_in_device_id', 'time_in_ip', 'time_in_mock_location_flag',
        'time_in_distance_meters',
        'time_out', 'time_out_latitude', 'time_out_longitude', 'time_out_address',
        'time_out_selfie', 'time_out_device_id', 'time_out_ip', 'time_out_mock_location_flag',
        'time_out_distance_meters',
        'working_hours', 'status', 'is_regularized', 'office_id', 'missing_time_out',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'time_in' => 'datetime',
            'time_out' => 'datetime',
            'time_in_latitude' => 'float',
            'time_in_longitude' => 'float',
            'time_out_latitude' => 'float',
            'time_out_longitude' => 'float',
            'time_in_mock_location_flag' => 'boolean',
            'time_out_mock_location_flag' => 'boolean',
            'is_regularized' => 'boolean',
            'missing_time_out' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function timeInDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'time_in_device_id');
    }

    public function timeOutDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'time_out_device_id');
    }
}
