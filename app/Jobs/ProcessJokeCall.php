<?php

namespace App\Jobs;

use App\Enums\JokeCallStatus;
use App\Events\JokeCallStatusUpdated;
use App\Models\JokeCall;
use App\Services\ClaudeJokeService;
use App\Services\ElevenLabsService;
use App\Services\TwilioCallService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessJokeCall implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 2;

    public function __construct(
        public JokeCall $jokeCall,
    ) {
        $this->onQueue('joke-calls');
    }

    public function handle(
        ClaudeJokeService $claude,
        ElevenLabsService $elevenlabs,
        TwilioCallService $twilio,
    ): void {
        try {
            $scenario = $this->jokeCall->custom_joke_prompt ?? $this->jokeCall->joke_text ?? '';

            // Step 1: Generate prank script from scenario
            $this->updateStatus(JokeCallStatus::GeneratingJoke);
            $prankScript = $claude->generatePrankScript($scenario);

            // Store the script as joke_text (the opening line) and full script in transcript
            $this->jokeCall->update([
                'joke_text' => $prankScript['opening'],
                'ai_transcript' => [
                    'prank_script' => $prankScript,
                    'scenario' => $scenario,
                    'conversation' => [],
                ],
            ]);

            // WhatsApp delivery — send a text prank message
            if ($this->jokeCall->delivery_type === 'whatsapp') {
                $this->updateStatus(JokeCallStatus::QueuedForCall);
                $whatsapp = app(\App\Services\WhatsAppService::class);
                $sid = $whatsapp->sendPrank($this->jokeCall, $prankScript);
                $this->jokeCall->update([
                    'twilio_call_sid' => $sid,
                    'status' => JokeCallStatus::Completed,
                ]);
                broadcast(new JokeCallStatusUpdated($this->jokeCall));
                return;
            }

            // Step 2: Generate opening audio
            $this->updateStatus(JokeCallStatus::GeneratingAudio);
            $audioPath = $elevenlabs->synthesize($prankScript['opening']);
            $this->jokeCall->update(['audio_file_path' => $audioPath]);

            // Step 3: Initiate call
            $this->updateStatus(JokeCallStatus::QueuedForCall);
            $callSid = $twilio->initiateCall($this->jokeCall);
            $this->jokeCall->update([
                'twilio_call_sid' => $callSid,
                'status' => JokeCallStatus::Calling,
            ]);
            broadcast(new JokeCallStatusUpdated($this->jokeCall));

        } catch (\Throwable $e) {
            Log::error('ProcessJokeCall failed', [
                'joke_call_id' => $this->jokeCall->id,
                'error' => $e->getMessage(),
            ]);

            $this->jokeCall->update([
                'status' => JokeCallStatus::Failed,
                'failure_reason' => $e->getMessage(),
            ]);
            broadcast(new JokeCallStatusUpdated($this->jokeCall));

            throw $e;
        }
    }

    private function updateStatus(JokeCallStatus $status): void
    {
        $this->jokeCall->updateStatus($status);
        broadcast(new JokeCallStatusUpdated($this->jokeCall));
    }
}
