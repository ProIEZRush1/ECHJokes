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
            'ref' => 'nullable|string|max:16',
        ]);

        $referrer = null;
        $refCode = $request->ref ?: $request->cookie('vacilada_ref') ?: session('echjokes_ref');
        if ($refCode) {
            $referrer = \App\Models\User::where('referral_code', strtoupper($refCode))->first();
        }

        $user = \App\Models\User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'email_verified_at' => now(),
            'referred_by_user_id' => $referrer?->id,
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
        $request->validate(['plan_id' => 'required|exists:plans,id']);

        $plan = Plan::findOrFail($request->plan_id);
        $user = Auth::user();

        Stripe::setApiKey(config('services.stripe.secret'));

        // Calculate upgrade discount
        $currentPlan = $user->subscription_plan
            ? Plan::where('slug', $user->subscription_plan)->first()
            : null;

        $usedCalls = $currentPlan
            ? JokeCall::where('user_id', $user->id)->where('joke_source', 'paid')->count()
            : 0;

        $discount = 0;
        if ($currentPlan && $plan->price_mxn > $currentPlan->price_mxn) {
            // Upgrade: discount = what they paid minus proportional value of used calls
            $perCallValue = $currentPlan->calls_included > 0
                ? $currentPlan->price_mxn / $currentPlan->calls_included
                : 0;
            $unusedValue = max(0, $currentPlan->price_mxn - ($usedCalls * $perCallValue));
            $discount = round($unusedValue, 2);
        }

        $finalPrice = max(100, round(($plan->price_mxn - $discount) * 100)); // centavos, min $1 MXN

        $checkout = StripeSession::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'mxn',
                    'unit_amount' => (int) $finalPrice,
                    'product_data' => [
                        'name' => "Vacilada {$plan->name}" . ($discount > 0 ? " (Upgrade)" : ""),
                        'description' => "{$plan->calls_included} llamadas de broma" . ($discount > 0 ? " - Descuento de \${$discount} MXN aplicado" : ""),
                    ],
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => url('/dashboard?purchased=1'),
            'cancel_url' => url('/pricing'),
            'customer_email' => $user->email,
            'metadata' => [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'plan_slug' => $plan->slug,
                'calls_included' => $plan->calls_included,
                'discount_applied' => $discount,
                'type' => $discount > 0 ? 'upgrade' : 'purchase',
            ],
        ]);

        return response()->json([
            'checkout_url' => $checkout->url,
            'discount' => $discount,
            'final_price' => $finalPrice / 100,
        ]);
    }

    public function buyCustom(Request $request): JsonResponse
    {
        $request->validate([
            'calls' => 'required|integer|min:1|max:50',
            'minutes' => 'required|integer|min:1|max:10',
        ]);

        $user = Auth::user();
        $calls = $request->input('calls');
        $minutes = $request->input('minutes');

        // Price calculation: base $14 MXN cost/call + 30% margin, scaled by minutes
        // 3 min = base, each extra minute adds ~$7 MXN
        $basePerCall = 26; // $26 MXN for 3 min (with ElevenLabs TTS)
        $extraPerMin = 7;  // $7 per extra minute
        $perCall = $basePerCall + max(0, ($minutes - 3) * $extraPerMin);
        $totalMxn = $perCall * $calls;

        Stripe::setApiKey(config('services.stripe.secret'));

        $checkout = StripeSession::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'mxn',
                    'unit_amount' => (int) ($totalMxn * 100),
                    'product_data' => [
                        'name' => "Vacilada - {$calls} broma" . ($calls > 1 ? 's' : '') . " ({$minutes} min c/u)",
                        'description' => "{$calls} llamadas de broma de hasta {$minutes} minutos",
                    ],
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => url('/dashboard?purchased=1'),
            'cancel_url' => url('/pricing'),
            'customer_email' => $user->email,
            'metadata' => [
                'user_id' => $user->id,
                'plan_slug' => 'custom',
                'calls_included' => $calls,
                'max_minutes' => $minutes,
                'type' => 'custom',
            ],
        ]);

        return response()->json([
            'checkout_url' => $checkout->url,
            'total_mxn' => $totalMxn,
            'per_call_mxn' => $perCall,
        ]);
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
            'victim_name' => $request->input('victim_name'),
            'delivery_type' => 'call',
            'voice' => $voice,
            'status' => JokeCallStatus::Calling,
            'ip_address' => $request->ip(),
            'user_id' => $user->id,
        ]);

        // Deduct credit
        $credit = $user->credit;
        if ($credit) {
            $credit->decrement('credits_remaining');
        }

        // Referral reward: first successful call credits both referrer and referee
        if ($user->referred_by_user_id && !$user->referral_credited_at) {
            $referrer = \App\Models\User::find($user->referred_by_user_id);
            if ($referrer) {
                \App\Models\UserCredit::firstOrCreate(['user_id' => $referrer->id], ['credits_remaining' => 0])->increment('credits_remaining', 2);
                \App\Models\UserCredit::firstOrCreate(['user_id' => $user->id], ['credits_remaining' => 0])->increment('credits_remaining', 2);
                $user->referral_credited_at = now();
                $user->save();
            }
        }

        try {
            $twilio = new \Twilio\Rest\Client(
                config('services.twilio.sid'),
                config('services.twilio.auth_token')
            );

            $plan = Plan::where('slug', $user->subscription_plan)->first();
            $maxDuration = $plan ? $plan->max_duration_minutes * 60 : 300;

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
