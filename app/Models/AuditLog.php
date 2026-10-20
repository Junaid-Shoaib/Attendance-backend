<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['user_id', 'action', 'ip_address', 'device_info', 'meta'];

    protected function casts(): array
    {
        return [
            'device_info' => 'array',
            'meta' => 'array',
        ];
    }

    public static function record(?int $userId, string $action, ?string $ip = null, array $deviceInfo = [], array $meta = []): self
    {
        return self::create([
            'user_id' => $userId,
            'action' => $action,
            'ip_address' => $ip,
            'device_info' => $deviceInfo,
            'meta' => $meta,
        ]);
    }
}
