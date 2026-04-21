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

class VaciladaController extends Controller
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

        $moderation = app(\App\Services\ContentModerationService::class)->check((string) $request->input('scenario'));
        if (!$moderation['allowed']) {
            return response()->json(app(\App\Services\ContentModerationService::class)->rejectionResponse($moderation), 422);
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
     * Free trial: 1 call per IP, max 3 min, no payment required.
     */
    public function trialCall(Request $request): JsonResponse
    {
        $request->validate([
            'phone_number' => 'required|string|regex:/^[1-9]\d{9}$/',
            'scenario' => 'required|string|min:10|max:500',
            'character' => 'nullable|string|max:200',
            'voice' => 'nullable|in:ash,coral',
        ]);

        $ip = $request->ip();

        // Check if this IP already used their free trial
        $existing = JokeCall::where('ip_address', $ip)
            ->where('joke_source', 'trial')
            ->whereNotIn('status', [JokeCallStatus::Failed, JokeCallStatus::Voicemail])
            ->count();

        if ($existing >= 1) {
            return response()->json([
                'error' => 'Ya usaste tu llamada de prueba gratuita. Adquiere un plan para seguir bromeando.',
                'show_plans' => true,
            ], 429);
        }

        $phone = '+52' . $request->input('phone_number');
        $scenario = strip_tags($request->input('scenario'));
        $character = strip_tags($request->input('character', ''));
        $voice = $request->input('voice', 'ash');

        $moderation = app(\App\Services\ContentModerationService::class)->check($scenario);
        if (!$moderation['allowed']) {
            return response()->json(app(\App\Services\ContentModerationService::class)->rejectionResponse($moderation), 422);
        }

        $jokeCall = JokeCall::create([
            'session_id' => Str::ulid()->toBase32(),
            'phone_number' => $phone,
            'joke_category' => 'prank',
            'joke_source' => 'trial',
            'custom_joke_prompt' => $scenario,
            'delivery_type' => 'call',
            'voice' => $voice,
            'status' => JokeCallStatus::Calling,
            'ip_address' => $ip,
        ]);

        try {
            $twilio = new \Twilio\Rest\Client(
                config('services.twilio.sid'),
                config('services.twilio.auth_token')
            );

            $call = $twilio->calls->create($phone, config('services.twilio.phone_number'), [
                'url' => url('/conversation/start') . '?scenario=' . urlencode($scenario) . '&character=' . urlencode($character) . '&voice=' . urlencode($voice) . '&victim_name=' . urlencode($request->input('victim_name', '')),
                'method' => 'POST',
                'statusCallback' => route('twilio.status'),
                'statusCallbackEvent' => ['initiated', 'ringing', 'answered', 'completed'],
                'statusCallbackMethod' => 'POST',
                'machineDetection' => 'Enable',
                'asyncAmd' => 'true',
                'asyncAmdStatusCallback' => route('twilio.status'),
                'asyncAmdStatusCallbackMethod' => 'POST',
                'timeout' => 30,
                'timeLimit' => 180, // 3 min max for trial
                'record' => true,
                'recordingStatusCallback' => route('twilio.recording'),
                'recordingStatusCallbackEvent' => ['completed'],
            ]);

            $jokeCall->update(['twilio_call_sid' => $call->sid]);

            return response()->json([
                'id' => $jokeCall->id,
                'call_sid' => $call->sid,
                'redirect' => "/call/{$jokeCall->id}/status",
            ]);
        } catch (\Throwable $e) {
            $jokeCall->update([
                'status' => JokeCallStatus::Failed,
                'failure_reason' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'No se pudo hacer la llamada. Intenta de nuevo.'], 500);
        }
    }

    public function callStatus(JokeCall $jokeCall): JsonResponse|\Illuminate\View\View
    {
        if (request()->wantsJson()) {
            $transcript = $jokeCall->ai_transcript ?? [];
            $conversation = $transcript['conversation'] ?? [];

            // Include live transcript for active calls
            $liveTranscript = [];
            if ($jokeCall->live_transcript) {
                $liveTranscript = json_decode($jokeCall->live_transcript, true) ?: [];
            }

            return response()->json([
                'id' => $jokeCall->id,
                'session_id' => $jokeCall->session_id,
                'status' => $jokeCall->status->value,
                'status_label' => $jokeCall->status->label(),
                'is_terminal' => $jokeCall->status->isTerminal(),
                'scenario' => $jokeCall->custom_joke_prompt,
                'opening_line' => $jokeCall->joke_text,
                'conversation' => $jokeCall->status->isTerminal() ? $conversation : $liveTranscript,
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
