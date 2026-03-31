<?php

namespace App\Http\Controllers;

use App\Enums\JokeCallStatus;
use App\Events\JokeCallStatusUpdated;
use App\Jobs\ProcessJokeCall;
use App\Models\JokeCall;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                config('services.stripe.webhook_secret')
            );
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed');
            return response('Invalid signature', 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $jokeCallId = $session->metadata->joke_call_id ?? null;

            if (! $jokeCallId) {
                Log::warning('Stripe webhook missing joke_call_id metadata');
                return response('OK', 200);
            }

            $jokeCall = JokeCall::find($jokeCallId);

            if (! $jokeCall) {
                Log::warning('Stripe webhook: JokeCall not found', ['id' => $jokeCallId]);
                return response('OK', 200);
            }

            // Idempotency: only process if still pending
            if ($jokeCall->status !== JokeCallStatus::PendingPayment) {
                return response('OK', 200);
            }

            $jokeCall->update([
                'status' => JokeCallStatus::Paid,
                'stripe_payment_intent_id' => $session->payment_intent,
            ]);

            broadcast(new JokeCallStatusUpdated($jokeCall));

            ProcessJokeCall::dispatch($jokeCall);
        }

        return response('OK', 200);
    }
}
