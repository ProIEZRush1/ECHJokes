<?php

namespace App\Jobs;

use App\Enums\JokeCallStatus;
use App\Models\JokeCall;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Stripe\Refund;
use Stripe\Stripe;

class HandleCallOutcomeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;

    public function __construct(
        public JokeCall $jokeCall,
        public string $callStatus,
    ) {
        $this->onQueue('recordings');
    }

    public function handle(): void
    {
        match ($this->callStatus) {
            'no-answer' => $this->handleNoAnswer(),
            'busy' => $this->handleBusy(),
            'failed' => $this->handleFailed(),
            default => null,
        };
    }

    private function handleNoAnswer(): void
    {
        // Mark as failed with reason, user can retry
        $this->jokeCall->update([
            'status' => JokeCallStatus::Failed,
            'failure_reason' => 'No contestaron. Puedes intentar de nuevo.',
        ]);
    }

    private function handleBusy(): void
    {
        $this->jokeCall->update([
            'status' => JokeCallStatus::Failed,
            'failure_reason' => 'Linea ocupada. Intenta en unos minutos.',
        ]);
    }

    private function handleFailed(): void
    {
        // Auto-refund on failed calls
        if ($this->jokeCall->stripe_payment_intent_id) {
            try {
                Stripe::setApiKey(config('services.stripe.secret'));
                Refund::create([
                    'payment_intent' => $this->jokeCall->stripe_payment_intent_id,
                ]);

                $this->jokeCall->update([
                    'status' => JokeCallStatus::Refunded,
                    'failure_reason' => 'La llamada fallo. Se proceso tu reembolso automaticamente.',
                ]);
            } catch (\Throwable $e) {
                Log::error('Auto-refund failed', [
                    'joke_call_id' => $this->jokeCall->id,
                    'error' => $e->getMessage(),
                ]);
                $this->jokeCall->update([
                    'status' => JokeCallStatus::Failed,
                    'failure_reason' => 'La llamada fallo. Contacta soporte para tu reembolso.',
                ]);
            }
        }
    }
}
