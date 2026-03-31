<?php

namespace App\Http\Controllers;

use App\Enums\JokeCallStatus;
use App\Http\Requests\CreateJokeCallRequest;
use App\Models\JokeCall;
use App\Services\TwilioCallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Stripe;

class ECHJokesController extends Controller
{
    public function index()
    {
        return view('app');
    }

    public function createCheckout(CreateJokeCallRequest $request): JsonResponse
    {
        $phone = $request->e164PhoneNumber();

        // Twilio Lookup
        $twilio = app(TwilioCallService::class);
        $lookup = $twilio->lookupPhoneNumber($phone);
        $phoneType = $lookup['type'];

        if ($phoneType === 'voip' && $request->input('delivery_type', 'call') === 'call') {
            return response()->json([
                'errors' => ['phone_number' => ['Numeros VoIP no son compatibles con llamadas. Intenta con WhatsApp.']],
            ], 422);
        }

        $jokeCall = JokeCall::create([
            'session_id' => Str::ulid()->toBase32(),
            'phone_number' => $phone,
            'joke_category' => 'prank',
            'joke_source' => 'custom',
            'custom_joke_prompt' => strip_tags($request->input('scenario')),
            'delivery_type' => $request->input('delivery_type', 'call'),
            'is_gift' => $request->boolean('is_gift'),
            'recipient_phone' => $request->e164RecipientPhone(),
            'sender_name' => $request->input('sender_name'),
            'gift_message' => $request->input('gift_message'),
            'status' => JokeCallStatus::PendingPayment,
            'ip_address' => $request->ip(),
            'phone_type' => $phoneType,
            'user_id' => auth()->id(),
        ]);

        Stripe::setApiKey(config('services.stripe.secret'));

        $priceId = config('services.stripe.price_id');
        if ($jokeCall->is_gift && env('STRIPE_GIFT_PRICE_ID')) {
            $priceId = env('STRIPE_GIFT_PRICE_ID');
        }

        $checkout = StripeSession::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => url("/call/{$jokeCall->id}/status"),
            'cancel_url' => url('/'),
            'metadata' => [
                'joke_call_id' => $jokeCall->id,
            ],
        ]);

        $jokeCall->update(['stripe_checkout_session_id' => $checkout->id]);

        return response()->json(['checkout_url' => $checkout->url]);
    }

    /**
     * Test mode: skip Stripe, directly create a call (local env only).
     */
    public function testCall(Request $request): JsonResponse
    {
        if (! app()->environment('local', 'testing')) {
            abort(404);
        }

        $request->validate([
            'phone_number' => 'required|string|regex:/^[1-9]\d{9}$/',
            'scenario' => 'required|string|min:10|max:500',
        ]);

        $jokeCall = JokeCall::create([
            'session_id' => Str::ulid()->toBase32(),
            'phone_number' => '+52' . $request->phone_number,
            'joke_category' => 'prank',
            'joke_source' => 'custom',
            'custom_joke_prompt' => strip_tags($request->scenario),
            'delivery_type' => $request->input('delivery_type', 'call'),
            'status' => JokeCallStatus::Paid,
            'ip_address' => $request->ip(),
        ]);

        // Dispatch directly (sync for testing)
        \App\Jobs\ProcessJokeCall::dispatchSync($jokeCall);

        return response()->json([
            'id' => $jokeCall->id,
            'status' => $jokeCall->fresh()->status->value,
            'joke_text' => $jokeCall->fresh()->joke_text,
            'redirect' => "/call/{$jokeCall->id}/status",
        ]);
    }

    public function callStatus(JokeCall $jokeCall): JsonResponse|null
    {
        if (request()->wantsJson()) {
            $transcript = $jokeCall->ai_transcript ?? [];
            $conversation = $transcript['conversation'] ?? [];

            return response()->json([
                'id' => $jokeCall->id,
                'session_id' => $jokeCall->session_id,
                'status' => $jokeCall->status->value,
                'status_label' => $jokeCall->status->label(),
                'is_terminal' => $jokeCall->status->isTerminal(),
                'scenario' => $jokeCall->custom_joke_prompt,
                'opening_line' => $jokeCall->joke_text,
                'conversation' => $jokeCall->status->isTerminal() ? $conversation : [],
                'call_duration_seconds' => $jokeCall->call_duration_seconds,
                'failure_reason' => $jokeCall->failure_reason,
                'recording_url' => $jokeCall->recording_url,
                'delivery_type' => $jokeCall->delivery_type,
                'is_gift' => $jokeCall->is_gift,
            ]);
        }

        return view('app');
    }
}
