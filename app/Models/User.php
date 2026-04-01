<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'is_admin',
        'stripe_customer_id',
        'subscription_plan',
        'subscription_ends_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'subscription_ends_at' => 'datetime',
        ];
    }

    public function jokeCalls(): HasMany
    {
        return $this->hasMany(JokeCall::class);
    }

    public function credit(): HasOne
    {
        return $this->hasOne(UserCredit::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_user_id');
    }

    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscription_plan
            && $this->subscription_ends_at
            && $this->subscription_ends_at->isFuture();
    }

    public function creditsRemaining(): int
    {
        return $this->credit?->credits_remaining ?? 0;
    }
}
