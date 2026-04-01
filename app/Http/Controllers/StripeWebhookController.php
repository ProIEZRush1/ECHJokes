<?php

namespace App\Http\Controllers;

use App\Enums\JokeCallStatus;
use App\Events\JokeCallStatusUpdated;
use App\Jobs\ProcessJokeCall;
use App\Models\JokeCall;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserCredit;
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
        $webhookSecret = config('services.stripe.webhook_secret');

        // If webhook secret is set, verify signature
        if ($webhookSecret) {
            try {
                $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
            } catch (SignatureVerificationException $e) {
                Log::warning('Stripe webhook signature verification failed');
                return response('Invalid signature', 400);
            }
        } else {
            $event = json_decode($payload);
        }

        $type = is_object($event) && property_exists($event, 'type') ? $event->type : ($event['type'] ?? null);

        if ($type === 'checkout.session.completed') {
            $session = is_object($event) && property_exists($event, 'data')
                ? $event->data->object
                : (object) ($event['data']['object'] ?? []);

            $metadata = (object) ($session->metadata ?? []);

            // Plan purchase — add credits
            if (!empty($metadata->plan_id)) {
                $this->handlePlanPurchase($metadata, $session);
                return response('OK', 200);
            }

            // Legacy: single call purchase
            $jokeCallId = $metadata->joke_call_id ?? null;
            if ($jokeCallId) {
                $this->handleCallPurchase($jokeCallId, $session);
            }
        }

        return response('OK', 200);
    }

    private function handlePlanPurchase(object $metadata, object $session): void
    {
        $userId = $metadata->user_id ?? null;
        $planId = $metadata->plan_id ?? null;
        $callsIncluded = (int) ($metadata->calls_included ?? 0);
        $planSlug = $metadata->plan_slug ?? null;

        if (!$userId || !$callsIncluded) {
            Log::warning('Stripe plan purchase: missing metadata', (array) $metadata);
            return;
        }

        $user = User::find($userId);
        if (!$user) {
            Log::warning('Stripe plan purchase: user not found', ['user_id' => $userId]);
            return;
        }

        // Add credits
        $credit = UserCredit::firstOrCreate(
            ['user_id' => $user->id],
            ['credits_remaining' => 0]
        );
        $credit->increment('credits_remaining', $callsIncluded);

        // Update user's plan
        $user->update([
            'subscription_plan' => $planSlug,
            'stripe_customer_id' => $session->customer ?? $user->stripe_customer_id,
        ]);

        Log::info('Plan purchased', [
            'user_id' => $userId,
            'plan' => $planSlug,
            'credits_added' => $callsIncluded,
            'total_credits' => $credit->fresh()->credits_remaining,
        ]);
    }

    private function handleCallPurchase(string $jokeCallId, object $session): void
    {
        $jokeCall = JokeCall::find($jokeCallId);
        if (!$jokeCall) {
            Log::warning('Stripe webhook: JokeCall not found', ['id' => $jokeCallId]);
            return;
        }

        if ($jokeCall->status !== JokeCallStatus::PendingPayment) {
            return;
        }

        $jokeCall->update([
            'status' => JokeCallStatus::Paid,
            'stripe_payment_intent_id' => $session->payment_intent ?? null,
        ]);

        broadcast(new JokeCallStatusUpdated($jokeCall));
        ProcessJokeCall::dispatch($jokeCall);
    }
}
