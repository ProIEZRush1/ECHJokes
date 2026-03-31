<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiCallLog extends Model
{
    protected $fillable = [
        'service',
        'endpoint',
        'method',
        'status_code',
        'latency_ms',
        'cost_estimate',
        'joke_call_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'cost_estimate' => 'decimal:4',
        ];
    }
}
