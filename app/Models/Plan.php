<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_mxn',
        'calls_included',
        'max_duration_minutes',
        'features',
        'is_popular',
        'is_active',
        'sort_order',
        'stripe_price_id',
    ];

    protected function casts(): array
    {
        return [
            'price_mxn' => 'decimal:2',
            'features' => 'array',
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function pricePerCall(): float
    {
        return $this->calls_included > 0 ? round($this->price_mxn / $this->calls_included, 2) : 0;
    }
}
