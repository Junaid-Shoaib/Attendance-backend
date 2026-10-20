<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    protected $fillable = [
        'user_id', 'device_id', 'device_name', 'platform', 'os_version',
        'app_version', 'is_rooted_or_jailbroken', 'is_primary', 'is_approved',
        'approved_at', 'approved_by', 'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'is_rooted_or_jailbroken' => 'boolean',
            'is_primary' => 'boolean',
            'is_approved' => 'boolean',
            'approved_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
