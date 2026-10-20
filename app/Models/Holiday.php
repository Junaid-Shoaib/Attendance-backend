<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = ['name', 'date', 'is_recurring_yearly'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
