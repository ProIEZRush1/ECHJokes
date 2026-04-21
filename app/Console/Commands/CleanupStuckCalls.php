<?php

namespace App\Console\Commands;

use App\Enums\JokeCallStatus;
use App\Models\JokeCall;
use App\Models\UserCredit;
use Illuminate\Console\Command;

class CleanupStuckCalls extends Command
{
    protected $signature = 'vacilada:cleanup-stuck-calls {--minutes=10 : Mark as failed if older than N min}';
    protected $description = 'Mark stuck calling/in-progress calls as failed and refund credits';

    public function handle(): int
    {
        $threshold = now()->subMinutes((int) $this->option('minutes'));

        $stuck = JokeCall::whereIn('status', [JokeCallStatus::Calling, JokeCallStatus::InProgress])
            ->where('created_at', '<', $threshold)
            ->get();

        if ($stuck->isEmpty()) {
            $this->info('No stuck calls found.');
            return 0;
        }

        $refunded = 0;
        $recovered = 0;
        $this->info("Found {$stuck->count()} stuck calls.");

        $twilio = null;
        try {
            $twilio = new \Twilio\Rest\Client(config('services.twilio.sid'), config('services.twilio.auth_token'));
        } catch (\Throwable $e) {
            $this->warn('Twilio client unavailable — cannot recover recordings');
        }

        foreach ($stuck as $call) {
            // Attempt to recover recording from Twilio before marking failed
            $gotRecording = false;
            if ($twilio && $call->twilio_call_sid) {
                try {
                    $recs = $twilio->recordings->read(['callSid' => $call->twilio_call_sid], 5);
                    foreach ($recs as $r) {
                        if ((int) $r->duration > 0) {
                            $url = 'https://api.twilio.com/2010-04-01/Accounts/' . $r->accountSid . '/Recordings/' . $r->sid . '.mp3';
                            $call->update([
                                'status' => JokeCallStatus::Completed,
                                'recording_url' => $url,
                                'recording_sid' => $r->sid,
                                'recording_duration_sec' => (int) $r->duration,
                                'call_duration_seconds' => (int) $r->duration,
                                'failure_reason' => null,
                            ]);
                            $this->line("  Recovered call {$call->id} — {$r->duration}s recording, marked completed");
                            $gotRecording = true;
                            $recovered++;
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    $this->warn("  Could not fetch Twilio recording for {$call->id}: " . $e->getMessage());
                }
            }

            if ($gotRecording) continue;

            // No recording → mark failed and refund
            $call->update([
                'status' => JokeCallStatus::Failed,
                'failure_reason' => 'Timeout (stuck in ' . $call->status->value . ' for >' . $this->option('minutes') . 'min)',
            ]);

            if ($call->user_id) {
                $credit = UserCredit::where('user_id', $call->user_id)->first();
                if ($credit) {
                    $credit->increment('credits_remaining', 1);
                    $refunded++;
                }
            }

            $this->line("  Failed call {$call->id} (no recording), credit refunded");
        }

        $this->info("Cleaned {$stuck->count()} calls: {$recovered} recovered with recording, {$refunded} credits refunded.");
        return 0;
    }
}
