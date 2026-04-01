<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Preset extends Model
{
    protected $fillable = [
        'label',
        'emoji',
        'scenario',
        'character',
        'voice',
        'style',
        'category',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
