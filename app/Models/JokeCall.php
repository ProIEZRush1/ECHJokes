<?php

namespace App\Models;

use App\Enums\JokeCallStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class JokeCall extends Model
{
    use HasUlids;

    protected $fillable = [
        'session_id',
        'share_slug',
        'is_public',
        'share_views',
        'share_clicks',
        'phone_number',
        'joke_category',
        'joke_source',
        'custom_joke_prompt',
        'victim_name',
        'delivery_type',
        'is_gift',
        'recipient_phone',
        'sender_name',
        'gift_message',
        'status',
        'joke_text',
        'audio_file_path',
        'stripe_payment_intent_id',
        'stripe_checkout_session_id',
        'twilio_call_sid',
        'stream_sid',
        'call_duration_seconds',
        'ai_transcript',
        'reaction_sentiment',
        'recording_url',
        'recording_sid',
        'recording_duration_sec',
        'estimated_cost_usd',
        'failure_reason',
        'live_transcript',
        'retry_of',
        'voice',
        'ip_address',
        'phone_type',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => JokeCallStatus::class,
            'ai_transcript' => 'array',
            'is_gift' => 'boolean',
            'is_public' => 'boolean',
            'estimated_cost_usd' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (JokeCall $call) {
            if (empty($call->share_slug)) {
                do {
                    $slug = strtolower(\Illuminate\Support\Str::random(8));
                } while (self::where('share_slug', $slug)->exists());
                $call->share_slug = $slug;
            }
        });
    }

    public function updateStatus(JokeCallStatus $status): void
    {
        $this->update(['status' => $status]);
    }

    /**
     * Get the phone number to call (recipient if gift, otherwise phone_number).
     */
    public function callablePhone(): string
    {
        return $this->is_gift && $this->recipient_phone
            ? $this->recipient_phone
            : $this->phone_number;
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
