<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitorTouchpoint extends Model
{
    protected $fillable = [
        'visitor_id', 'user_id',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
        'referrer', 'landing_page', 'ip', 'user_agent',
        'is_first_touch',
    ];

    protected function casts(): array
    {
        return ['is_first_touch' => 'boolean'];
    }
}
