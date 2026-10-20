<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = ['name', 'code', 'is_active'];

    public function employees(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
