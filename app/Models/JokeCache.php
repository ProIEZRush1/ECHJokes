<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JokeCache extends Model
{
    protected $table = 'jokes_cache';

    protected $fillable = [
        'joke_text',
        'joke_hash',
        'language',
        'source',
        'use_count',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return ['fetched_at' => 'datetime'];
    }
}
