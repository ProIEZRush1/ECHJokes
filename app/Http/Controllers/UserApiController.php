<?php

namespace App\Http\Controllers;

use App\Enums\JokeCallStatus;
use App\Models\JokeCall;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Stripe;

class UserApiController extends Controller
{
    public function me(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'Not authenticated'], 401);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'credits' => $user->creditsRemaining(),
                'plan' => $user->subscription_plan,
                'is_admin' => $user->is_admin,
            ],
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $user = \App\Models\User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'email_verified_at' => now(),
        ]);

        Auth::login($user, true);

        return response()->json(['user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'credits' => 0,
        ]]);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'), true)) {
            return response()->json(['error' => 'Credenciales incorrectas'], 401);
        }

        $user = Auth::user();
        return response()->json(['user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'credits' => $user->creditsRemaining(),
            'plan' => $user->subscription_plan,
        ]]);
    }

    public function logout(): JsonResponse
    {
        Auth::logout();
        return response()->json(['ok' => true]);
    }

    public function plans(): JsonResponse
    {
        return response()->json(Plan::where('is_active', true)->orderBy('sort_order')->get());
    }

    public function myCalls(Request $request): JsonResponse
    {
        $user = Auth::user();
        $calls = JokeCall::where('user_id', $user->id)
            ->latest()
            ->paginate(15);

        return response()->json($calls);
    }

    public function myCall(JokeCall $jokeCall): JsonResponse
    {
        $user = Auth::user();
        if ($jokeCall->user_id !== $user->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $transcript = $jokeCall->live_transcript ? json_decode($jokeCall->live_transcript, true) : [];

        return response()->json([
            'call' => $jokeCall,
            'transcript' => $transcript,
        ]);
    }

    public function buyPlan(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'quantity' => 'nullable|integer|min:1|max:20',
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        if (!$plan->stripe_price_id) {
            return response()->json(['error' => 'Plan not configured for payments'], 400);
        }

        $user = Auth::user();
        $quantity = $request->input('quantity', 1);
        $totalCalls = $plan->calls_included * $quantity;

        Stripe::setApiKey(config('services.stripe.secret'));

        $checkout = StripeSession::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $plan->stripe_price_id,
                'quantity' => $quantity,
            ]],
            'mode' => 'payment',
            'success_url' => url('/dashboard?purchased=1'),
            'cancel_url' => url('/pricing'),
            'customer_email' => $user->email,
            'metadata' => [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'plan_slug' => $plan->slug,
                'calls_included' => $totalCalls,
            ],
        ]);

        return response()->json(['checkout_url' => $checkout->url]);
    }

    public function makeCall(Request $request): JsonResponse
    {
        $request->validate([
            'phone_number' => 'required|string|regex:/^[1-9]\d{9}$/',
            'scenario' => 'required|string|min:10|max:500',
            'character' => 'nullable|string|max:200',
            'voice' => 'nullable|in:ash,coral',
        ]);

        $user = Auth::user();
        $credits = $user->creditsRemaining();

        if ($credits <= 0) {
            return response()->json(['error' => 'No tienes creditos. Compra un plan para hacer llamadas.', 'show_plans' => true], 402);
        }

        $phone = '+52' . $request->input('phone_number');
        $scenario = strip_tags($request->input('scenario'));
        $character = strip_tags($request->input('character', ''));
        $voice = $request->input('voice', 'ash');

        $jokeCall = JokeCall::create([
            'session_id' => Str::ulid()->toBase32(),
            'phone_number' => $phone,
            'joke_category' => 'prank',
            'joke_source' => 'paid',
            'custom_joke_prompt' => $scenario,
            'delivery_type' => 'call',
            'status' => JokeCallStatus::Calling,
            'ip_address' => $request->ip(),
            'user_id' => $user->id,
        ]);

        // Deduct credit
        $credit = $user->credit;
        if ($credit) {
            $credit->decrement('credits_remaining');
        }

        try {
            $twilio = new \Twilio\Rest\Client(
                config('services.twilio.sid'),
                config('services.twilio.auth_token')
            );

            $plan = Plan::where('slug', $user->subscription_plan)->first();
            $maxDuration = $plan ? $plan->max_duration_minutes * 60 : 300;

            $call = $twilio->calls->create($phone, config('services.twilio.phone_number'), [
                'url' => url('/conversation/start') . '?scenario=' . urlencode($scenario) . '&character=' . urlencode($character) . '&voice=' . urlencode($voice),
                'method' => 'POST',
                'statusCallback' => route('twilio.status'),
                'statusCallbackEvent' => ['initiated', 'ringing', 'answered', 'completed'],
                'statusCallbackMethod' => 'POST',
                'machineDetection' => 'Enable',
                'asyncAmd' => 'true',
                'asyncAmdStatusCallback' => route('twilio.status'),
                'asyncAmdStatusCallbackMethod' => 'POST',
                'timeout' => 30,
                'timeLimit' => $maxDuration,
                'record' => true,
                'recordingStatusCallback' => route('twilio.recording'),
                'recordingStatusCallbackEvent' => ['completed'],
            ]);

            $jokeCall->update(['twilio_call_sid' => $call->sid]);

            return response()->json([
                'id' => $jokeCall->id,
                'redirect' => "/call/{$jokeCall->id}/status",
                'credits_remaining' => max(0, $credits - 1),
            ]);
        } catch (\Throwable $e) {
            // Refund credit on failure
            if ($credit) $credit->increment('credits_remaining');

            $jokeCall->update([
                'status' => JokeCallStatus::Failed,
                'failure_reason' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'No se pudo hacer la llamada.'], 500);
        }
    }
}
