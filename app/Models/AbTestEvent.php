<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbTestEvent extends Model
{
    protected $fillable = [
        'test_name', 'variant', 'event_type',
        'visitor_id', 'user_id', 'call_id', 'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
