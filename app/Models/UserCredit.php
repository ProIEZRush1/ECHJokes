<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCredit extends Model
{
    protected $fillable = [
        'user_id',
        'credits_remaining',
        'jokes_remaining',
        'jokes_reset_at',
        'plan_type',
        'resets_at',
    ];

    protected function casts(): array
    {
        return [
            'resets_at' => 'datetime',
            'jokes_reset_at' => 'datetime',
        ];
    }

    /**
     * Try to consume one joke. Auto-resets monthly quota if past reset date.
     * Returns true if allowed.
     */
    public function consumeJoke(): bool
    {
        if ($this->jokes_reset_at && $this->jokes_reset_at->isPast()) {
            // Monthly reset — free users get 5 back
            $freeQuota = $this->plan_type ? $this->jokes_remaining : 5;
            $this->update(['jokes_remaining' => $freeQuota, 'jokes_reset_at' => now()->addMonth()]);
            $this->refresh();
        }

        if ($this->jokes_remaining <= 0) return false;
        $this->decrement('jokes_remaining');
        return true;
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
