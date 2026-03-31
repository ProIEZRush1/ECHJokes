<?php

namespace App\Services;

use App\Models\JokeCall;

class CostTrackingService
{
    // Cost estimates per unit
    private const TWILIO_CALL_PER_MIN = 0.015;
    private const ELEVENLABS_PER_1K_CHARS = 0.30;
    private const DEEPGRAM_PER_MIN = 0.0059;
    private const CLAUDE_PER_CALL = 0.01;

    /**
     * Estimate the cost of a completed joke call.
     */
    public function estimateCost(JokeCall $jokeCall): float
    {
        $cost = 0.0;

        // Twilio call cost
        $durationMin = ($jokeCall->call_duration_seconds ?? 60) / 60;
        $cost += $durationMin * self::TWILIO_CALL_PER_MIN;

        // ElevenLabs TTS cost
        $jokeLength = strlen($jokeCall->joke_text ?? '');
        $cost += ($jokeLength / 1000) * self::ELEVENLABS_PER_1K_CHARS;

        // Deepgram STT cost (only for bidirectional calls)
        if ($jokeCall->stream_sid) {
            $cost += $durationMin * self::DEEPGRAM_PER_MIN;
        }

        // Claude API cost
        $cost += self::CLAUDE_PER_CALL;

        return round($cost, 4);
    }

    /**
     * Update the estimated cost on a joke call record.
     */
    public function updateCost(JokeCall $jokeCall): void
    {
        $jokeCall->update([
            'estimated_cost_usd' => $this->estimateCost($jokeCall),
        ]);
    }
}
