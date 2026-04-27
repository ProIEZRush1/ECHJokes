<?php

namespace App\Http\Controllers;

use App\Enums\JokeCallStatus;
use App\Events\JokeCallStatusUpdated;
use App\Models\JokeCall;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class TwilioWebhookController extends Controller
{
    /**
     * Return TwiML — uses Media Streams for bidirectional conversation,
     * falls back to <Play> if stream server is not configured.
     */
    public function voice(JokeCall $jokeCall): Response
    {
        $jokeCall->updateStatus(JokeCallStatus::InProgress);
        broadcast(new JokeCallStatusUpdated($jokeCall));

        $streamPort = env('MEDIA_STREAM_PORT');
        $appUrl = config('app.url');

        if ($streamPort) {
            // Phase 2: Bidirectional Media Streams
            $wsUrl = str_replace(['http://', 'https://'], ['ws://', 'wss://'], $appUrl);
            $streamUrl = "{$wsUrl}/stream/{$jokeCall->session_id}";

            $twiml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <Response>
                <Connect>
                    <Stream url="{$streamUrl}" />
                </Connect>
            </Response>
            XML;
        } else {
            // Phase 1 fallback: One-way audio playback
            $audioUrl = URL::signedRoute('audio.show', ['jokeCall' => $jokeCall->id], now()->addMinutes(10));

            $twiml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <Response>
                <Play>{$audioUrl}</Play>
                <Pause length="1"/>
                <Hangup/>
            </Response>
            XML;
        }

        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    /**
     * Handle Twilio recording status callback.
     */
    public function recording(Request $request): Response
    {
        $recordingUrl = $request->input('RecordingUrl');
        $recordingSid = $request->input('RecordingSid');
        $recordingDuration = (int) $request->input('RecordingDuration', 0);
        $callSid = $request->input('CallSid');

        $jokeCall = JokeCall::where('twilio_call_sid', $callSid)->first();

        if ($jokeCall) {
            $jokeCall->update([
                'recording_sid' => $recordingSid,
                'recording_duration_sec' => $recordingDuration,
                'recording_url' => $recordingUrl ? $recordingUrl . '.mp3' : null,
            ]);
        }

        return response('OK', 200);
    }

    /**
     * Handle Twilio status callback events.
     */
    public function status(Request $request): Response
    {
        try {
            $callSid = $request->input('CallSid');
            $callStatus = $request->input('CallStatus');

            $jokeCall = JokeCall::where('twilio_call_sid', $callSid)->first();

            if (! $jokeCall) {
                Log::warning('Twilio status callback: JokeCall not found', ['call_sid' => $callSid]);
                return response('OK', 200);
            }

            $answeredBy = $request->input('AnsweredBy');
            // Any machine/voicemail indication → hang up + refund
            if ($answeredBy && in_array($answeredBy, ['machine_start', 'machine_end_beep', 'machine_end_silence', 'machine_end_other', 'fax'])) {
                Log::info('Voicemail detected', ['call_sid' => $callSid, 'answered_by' => $answeredBy]);
                $jokeCall->update(['status' => JokeCallStatus::Voicemail, 'failure_reason' => 'Buzón de voz ('.$answeredBy.')']);
                $this->refundCredit($jokeCall);
                try {
                    $twilio = new \Twilio\Rest\Client(config('services.twilio.sid'), config('services.twilio.auth_token'));
                    $twilio->calls($callSid)->update(['status' => 'completed']);
                } catch (\Throwable $e) {
                    Log::warning('Could not hang up voicemail call', ['error' => $e->getMessage()]);
                }
                return response('OK', 200);
            }

            if ($jokeCall->status->isTerminal()) {
                return response('OK', 200);
            }

            match ($callStatus) {
                'in-progress' => $this->handleInProgress($jokeCall),
                'completed' => $this->handleCompleted($jokeCall, $request),
                'busy', 'no-answer', 'failed', 'canceled' => $this->handleFailed($jokeCall, $callStatus),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::error('Twilio status callback exception', [
                'error' => $e->getMessage(),
                'call_sid' => $request->input('CallSid'),
                'status' => $request->input('CallStatus'),
            ]);
            // ALWAYS return OK so Twilio doesn't think our webhook is broken
        }

        return response('OK', 200);
    }

    private function handleInProgress(JokeCall $jokeCall): void
    {
        $jokeCall->updateStatus(JokeCallStatus::InProgress);
        broadcast(new JokeCallStatusUpdated($jokeCall));
    }

    private function handleCompleted(JokeCall $jokeCall, Request $request): void
    {
        $duration = (int) $request->input('CallDuration', 0);

        // Sub-10-second prank calls don't charge the user. The conversation
        // didn't have time to actually happen — could be voicemail Twilio
        // missed, hangup right after pickup, AI cold-start failure, or just
        // a call that didn't go anywhere. Mark as too_short + refund.
        // Joke calls (one-way audio) are exempt — they're often that short
        // by design.
        $minBilledSeconds = $jokeCall->delivery_type === 'call' ? 10 : 2;
        if ($duration < $minBilledSeconds) {
            $reason = $duration === 0 ? 'no_connection' : 'too_short';
            $this->handleFailed($jokeCall, $reason);
            return;
        }

        // Post-call fallbacks for prank calls that "completed" but nothing
        // actually happened on the line.
        if ($jokeCall->delivery_type === 'call') {
            $diag = $this->diagnoseTranscript($jokeCall);

            // (a) Transcript completely empty — WS never connected or
            // OpenAI/ElevenLabs died on first message. Twilio played its
            // default "Sorry, an application error has occurred".
            if ($diag === 'empty') {
                Log::warning('Prank call completed with empty transcript — likely stream failure', [
                    'call_id' => $jokeCall->id,
                    'sid' => $jokeCall->twilio_call_sid,
                    'duration' => $duration,
                ]);
                $jokeCall->update([
                    'status' => JokeCallStatus::Failed,
                    'failure_reason' => 'Error técnico: la IA no se conectó. Crédito reembolsado.',
                    'call_duration_seconds' => $duration,
                ]);
                broadcast(new JokeCallStatusUpdated($jokeCall));
                $this->refundCredit($jokeCall);
                app(\App\Services\CostTrackingService::class)->updateCost($jokeCall);
                return;
            }

            // (b) AI spoke but the human never did. Voicemail that Twilio's
            // AMD missed — quiet greeting, music, unusual format. Threshold
            // dropped from 6s to 3s because voicemails reliably hang up fast
            // when our AI keeps talking; we want to catch those too.
            if ($diag === 'voicemail' && $duration >= 3) {
                Log::info('Voicemail detected post-call (no human turns)', [
                    'call_id' => $jokeCall->id,
                    'duration' => $duration,
                ]);
                $jokeCall->update([
                    'status' => JokeCallStatus::Voicemail,
                    'failure_reason' => 'Buzón de voz (detectado por ausencia de voz humana)',
                    'call_duration_seconds' => $duration,
                ]);
                broadcast(new JokeCallStatusUpdated($jokeCall));
                $this->refundCredit($jokeCall);
                app(\App\Services\CostTrackingService::class)->updateCost($jokeCall);
                return;
            }
        }

        $jokeCall->update([
            'status' => JokeCallStatus::Completed,
            'call_duration_seconds' => $duration,
        ]);
        broadcast(new JokeCallStatusUpdated($jokeCall));

        \App\Jobs\ClassifyReactionSentimentJob::dispatch($jokeCall);
        app(\App\Services\CostTrackingService::class)->updateCost($jokeCall);
    }

    /**
     * Classify a completed prank call by what's in its live transcript:
     *   'empty'     — no turns at all (WS never connected / AI died).
     *   'voicemail' — AI spoke but human never did (mailbox).
     *   'normal'    — has at least one human turn.
     */
    private function diagnoseTranscript(JokeCall $jokeCall): string
    {
        $raw = $jokeCall->live_transcript;
        if (!$raw) return 'empty';
        $turns = json_decode($raw, true);
        if (!is_array($turns) || count($turns) === 0) return 'empty';
        foreach ($turns as $t) {
            if (($t['role'] ?? '') === 'human') return 'normal';
        }
        return 'voicemail';
    }

    private function handleFailed(JokeCall $jokeCall, string $reason): void
    {
        // User-facing reason text. Specific causes get a friendlier message;
        // anything else falls back to the raw status code so admin can debug.
        $userMessage = match ($reason) {
            'too_short'     => 'La llamada duró menos de 10 segundos. No te cobramos crédito.',
            'no_connection' => 'La llamada no se conectó. Crédito reembolsado.',
            'no-answer'     => 'No contestaron. Crédito reembolsado.',
            'busy'          => 'Línea ocupada. Crédito reembolsado.',
            'canceled'      => 'Llamada cancelada. Crédito reembolsado.',
            'failed'        => 'Error de la operadora. Crédito reembolsado.',
            default         => "Call status: {$reason}",
        };

        $jokeCall->update([
            'status' => JokeCallStatus::Failed,
            'failure_reason' => $userMessage,
        ]);
        broadcast(new JokeCallStatusUpdated($jokeCall));

        // Refund credit for paid calls that failed (includes no-answer, busy,
        // failed, canceled, and 0-second completions).
        $this->refundCredit($jokeCall);

        // Dispatch outcome handler for refunds/retries
        \App\Jobs\HandleCallOutcomeJob::dispatch($jokeCall, $reason);
    }

    private function refundCredit(JokeCall $jokeCall): void
    {
        if ($jokeCall->joke_source !== 'paid' || !$jokeCall->user_id) return;

        $credit = \App\Models\UserCredit::where('user_id', $jokeCall->user_id)->first();
        if ($credit) {
            $credit->increment('credits_remaining');
            Log::info('Credit refunded for failed/voicemail call', [
                'user_id' => $jokeCall->user_id,
                'call_id' => $jokeCall->id,
                'reason' => $jokeCall->failure_reason,
            ]);
        }
    }
}
