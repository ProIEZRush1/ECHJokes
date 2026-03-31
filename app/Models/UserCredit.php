<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCredit extends Model
{
    protected $fillable = [
        'user_id',
        'credits_remaining',
        'plan_type',
        'resets_at',
    ];

    protected function casts(): array
    {
        return [
            'resets_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Deduct one credit. Returns false if no credits available.
     */
    public function deduct(): bool
    {
        if ($this->credits_remaining <= 0) {
            return false;
        }

        $this->decrement('credits_remaining');
        return true;
    }

    /**
     * Reset credits based on plan type.
     */
    public function resetForPlan(string $planType): void
    {
        $credits = match ($planType) {
            'bromista' => 5,
            'comediante' => 20,
            default => 0,
        };

        $this->update([
            'credits_remaining' => $credits,
            'plan_type' => $planType,
            'resets_at' => now()->addMonth(),
        ]);
    }
}
