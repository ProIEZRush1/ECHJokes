<?php

namespace App\Http\Controllers;

use App\Enums\JokeCallStatus;
use App\Models\JokeCall;
use App\Models\Plan;
use App\Models\Preset;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminApiController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'), true)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $user = Auth::user();
        if (!$user->is_admin) {
            Auth::logout();
            return response()->json(['error' => 'Not authorized'], 403);
        }

        return response()->json(['user' => $user]);
    }

    public function logout(): JsonResponse
    {
        Auth::logout();
        return response()->json(['ok' => true]);
    }

    public function me(): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !$user->is_admin) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }
        return response()->json(['user' => $user]);
    }

    public function stats(): JsonResponse
    {
        $today = now()->startOfDay();

        return response()->json([
            'calls_today' => JokeCall::where('created_at', '>=', $today)->count(),
            'completed_today' => JokeCall::where('created_at', '>=', $today)
                ->where('status', JokeCallStatus::Completed)->count(),
            'revenue_today' => JokeCall::where('created_at', '>=', $today)
                ->where('status', JokeCallStatus::Completed)
                ->sum('estimated_cost_usd') ?? 0,
            'active_now' => JokeCall::whereIn('status', [
                JokeCallStatus::Calling,
                JokeCallStatus::InProgress,
            ])->count(),
            'total_calls' => JokeCall::count(),
            'total_completed' => JokeCall::where('status', JokeCallStatus::Completed)->count(),
        ]);
    }

    public function calls(Request $request): JsonResponse
    {
        $query = JokeCall::query()->with('user:id,name,email')->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('phone_number', 'like', "%{$search}%")
                  ->orWhere('custom_joke_prompt', 'like', "%{$search}%")
                  ->orWhere('twilio_call_sid', 'like', "%{$search}%");
            });
        }

        $calls = $query->paginate($request->input('per_page', 20));

        return response()->json($calls);
    }

    public function call(JokeCall $jokeCall): JsonResponse
    {
        return response()->json($jokeCall);
    }

    public function launchCall(Request $request): JsonResponse
    {
        $request->validate([
            'phone_number' => 'required|string',
            'scenario' => 'required|string',
            'character' => 'nullable|string',
            'voice' => 'nullable|in:ash,coral',
        ]);

        $phone = $request->input('phone_number');
        if (!str_starts_with($phone, '+')) {
            $phone = '+52' . $phone;
        }

        $moderation = app(\App\Services\ContentModerationService::class)->check($request->input('scenario'));
        if (!$moderation['allowed']) {
            return response()->json(app(\App\Services\ContentModerationService::class)->rejectionResponse($moderation), 422);
        }

        \Illuminate\Support\Facades\Log::info('launchCall request', [
            'phone' => $phone,
            'victim_name' => $request->input('victim_name'),
            'character' => $request->input('character'),
            'voice' => $request->input('voice'),
        ]);

        $jokeCall = JokeCall::create([
            'session_id' => Str::ulid()->toBase32(),
            'phone_number' => $phone,
            'joke_category' => 'prank',
            'joke_source' => 'custom',
            'custom_joke_prompt' => $request->input('scenario'),
            'victim_name' => $request->input('victim_name'),
            'delivery_type' => 'call',
            'voice' => $request->input('voice', 'ash'),
            'status' => JokeCallStatus::Calling,
            'ip_address' => $request->ip(),
        ]);

        try {
            $twilio = new \Twilio\Rest\Client(
                config('services.twilio.sid'),
                config('services.twilio.auth_token')
            );

            $call = $twilio->calls->create($phone, config('services.twilio.phone_number'), [
                'url' => url('/conversation/start') . '?scenario=' . urlencode($request->input('scenario')) . '&character=' . urlencode($request->input('character', '')) . '&voice=' . urlencode($request->input('voice', 'ash')) . '&victim_name=' . urlencode($request->input('victim_name', '')),
                'method' => 'POST',
                'statusCallback' => route('twilio.status'),
                'statusCallbackEvent' => ['initiated', 'ringing', 'answered', 'completed'],
                'statusCallbackMethod' => 'POST',
                'machineDetection' => 'DetectMessageEnd', 'machineDetectionTimeout' => 10, 'machineDetectionSilenceTimeout' => 3000, 'machineDetectionSpeechEndThreshold' => 1500,
                'asyncAmd' => 'true',
                'asyncAmdStatusCallback' => route('twilio.status'),
                'asyncAmdStatusCallbackMethod' => 'POST',
                'timeout' => 45,
                'record' => true,
                'recordingStatusCallback' => route('twilio.recording'),
                'recordingStatusCallbackEvent' => ['completed'],
            ]);

            $jokeCall->update(['twilio_call_sid' => $call->sid]);

            return response()->json([
                'success' => true,
                'call_id' => $jokeCall->id,
                'call_sid' => $call->sid,
            ]);
        } catch (\Throwable $e) {
            $jokeCall->update([
                'status' => JokeCallStatus::Failed,
                'failure_reason' => $e->getMessage(),
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function users(Request $request): JsonResponse
    {
        $query = User::query()->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->withCount('jokeCalls')->paginate(20);

        return response()->json($users);
    }

    public function plans(): JsonResponse
    {
        return response()->json(Plan::orderBy('sort_order')->get());
    }

    public function createPlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:50|unique:plans,slug',
            'description' => 'nullable|string',
            'price_mxn' => 'required|numeric|min:0',
            'calls_included' => 'required|integer|min:1',
            'max_duration_minutes' => 'required|integer|min:1',
            'features' => 'nullable|array',
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $plan = Plan::create($data);
        return response()->json($plan, 201);
    }

    public function updatePlan(Request $request, Plan $plan): JsonResponse
    {
        $data = $request->validate([
            'name' => 'string|max:100',
            'description' => 'nullable|string',
            'price_mxn' => 'numeric|min:0',
            'calls_included' => 'integer|min:1',
            'max_duration_minutes' => 'integer|min:1',
            'features' => 'nullable|array',
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $plan->update($data);
        return response()->json($plan);
    }

    public function deletePlan(Plan $plan): JsonResponse
    {
        $plan->delete();
        return response()->json(['ok' => true]);
    }

    public function billing(): JsonResponse
    {
        // Twilio balance
        $twilioBalance = null;
        try {
            $twilio = new \Twilio\Rest\Client(config('services.twilio.sid'), config('services.twilio.auth_token'));
            $account = $twilio->api->v2010->accounts(config('services.twilio.sid'))->fetch();
            // Get balance via API
            $balance = $twilio->balance->fetch();
            $twilioBalance = [
                'balance' => $balance->balance,
                'currency' => $balance->currency,
            ];
        } catch (\Throwable $e) {
            $twilioBalance = ['error' => $e->getMessage()];
        }

        // Call stats
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();

        $totalCalls = JokeCall::count();
        $adminCalls = JokeCall::where('joke_source', 'custom')->count();
        $trialCalls = JokeCall::where('joke_source', 'trial')->count();
        $paidCalls = JokeCall::whereNotIn('joke_source', ['custom', 'trial'])->count();

        $callsToday = JokeCall::where('created_at', '>=', $today)->count();
        $callsThisMonth = JokeCall::where('created_at', '>=', $thisMonth)->count();

        $completedToday = JokeCall::where('created_at', '>=', $today)->where('status', JokeCallStatus::Completed)->count();
        $completedMonth = JokeCall::where('created_at', '>=', $thisMonth)->where('status', JokeCallStatus::Completed)->count();

        // Duration stats
        $totalMinutes = JokeCall::where('status', JokeCallStatus::Completed)->sum('call_duration_seconds') / 60;
        $monthMinutes = JokeCall::where('status', JokeCallStatus::Completed)->where('created_at', '>=', $thisMonth)->sum('call_duration_seconds') / 60;

        // Estimated costs: $0.49/min (Twilio $0.04 + OpenAI $0.14 + ElevenLabs $0.30 + recording $0.01)
        $costPerMinute = 0.49;
        $estimatedCostMonth = round($monthMinutes * $costPerMinute, 2);
        $estimatedCostTotal = round($totalMinutes * $costPerMinute, 2);

        // Revenue from Stripe (actual charges, net of fees)
        $revenueMxn = 0;
        $revenueNet = 0;
        try {
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            $charges = \Stripe\Charge::all(['limit' => 100, 'created' => ['gte' => $thisMonth->timestamp]]);
            foreach ($charges->data as $charge) {
                if ($charge->paid && !$charge->refunded) {
                    $revenueMxn += $charge->amount / 100; // centavos to MXN
                    // Net = amount - Stripe fee (3.6% + $3 MXN for Mexican cards)
                    $fee = round($charge->amount * 0.036 + 300); // centavos
                    $revenueNet += ($charge->amount - $fee) / 100;
                }
            }
        } catch (\Throwable $e) {
            // Fallback: estimate from users with plans
            $usersWithPlans = User::whereNotNull('subscription_plan')->get();
            foreach ($usersWithPlans as $u) {
                $plan = Plan::where('slug', $u->subscription_plan)->first();
                if ($plan) $revenueMxn += (float) $plan->price_mxn;
            }
            $revenueNet = round($revenueMxn * 0.964 - (count($usersWithPlans) * 3), 2);
        }
        $revenue = round($revenueMxn / 20, 2);

        // OpenAI — balance is not exposed by any API key. With an admin key
        // (sk-admin-…) we can pull month-to-date cost + usage breakdowns
        // (completion tokens + audio transcription seconds). Without an admin
        // key the regular project key returns 403 on every organization/*
        // endpoint, so we just show a link to the dashboard.
        $openAiInfo = ['dashboard_url' => 'https://platform.openai.com/settings/organization/billing/overview'];
        $adminKey = env('OPENAI_ADMIN_KEY');
        if ($adminKey && str_starts_with($adminKey, 'sk-admin-')) {
            $start = $thisMonth->timestamp;
            $headers = ['Authorization' => 'Bearer ' . $adminKey];

            // Month-to-date spend (USD) — from /v1/organization/costs.
            try {
                $resp = \Illuminate\Support\Facades\Http::withHeaders($headers)
                    ->timeout(5)
                    ->get('https://api.openai.com/v1/organization/costs', [
                        'start_time' => $start,
                        'bucket_width' => '1d',
                        'limit' => 31,
                    ]);
                if ($resp->ok()) {
                    $totalCents = 0;
                    foreach ($resp->json('data') ?? [] as $bucket) {
                        foreach ($bucket['results'] ?? [] as $row) {
                            $totalCents += (int) (($row['amount']['value'] ?? 0) * 100);
                        }
                    }
                    $openAiInfo['spent_month_usd'] = round($totalCents / 100, 2);
                } else {
                    $openAiInfo['costs_error'] = 'HTTP ' . $resp->status();
                }
            } catch (\Throwable $e) {
                $openAiInfo['costs_error'] = substr($e->getMessage(), 0, 120);
            }

            // Month-to-date token usage (LLM + Realtime text).
            try {
                $resp = \Illuminate\Support\Facades\Http::withHeaders($headers)
                    ->timeout(5)
                    ->get('https://api.openai.com/v1/organization/usage/completions', [
                        'start_time' => $start,
                        'bucket_width' => '1d',
                        'limit' => 31,
                    ]);
                if ($resp->ok()) {
                    $inputTokens = 0; $outputTokens = 0;
                    foreach ($resp->json('data') ?? [] as $bucket) {
                        foreach ($bucket['results'] ?? [] as $row) {
                            $inputTokens += (int) ($row['input_tokens'] ?? 0);
                            $outputTokens += (int) ($row['output_tokens'] ?? 0);
                        }
                    }
                    $openAiInfo['input_tokens_month'] = $inputTokens;
                    $openAiInfo['output_tokens_month'] = $outputTokens;
                }
            } catch (\Throwable $e) {
                // Non-fatal
            }

            // Month-to-date audio transcription seconds (Whisper + Realtime STT).
            try {
                $resp = \Illuminate\Support\Facades\Http::withHeaders($headers)
                    ->timeout(5)
                    ->get('https://api.openai.com/v1/organization/usage/audio_transcriptions', [
                        'start_time' => $start,
                        'bucket_width' => '1d',
                        'limit' => 31,
                    ]);
                if ($resp->ok()) {
                    $seconds = 0;
                    foreach ($resp->json('data') ?? [] as $bucket) {
                        foreach ($bucket['results'] ?? [] as $row) {
                            $seconds += (int) ($row['seconds'] ?? $row['num_seconds'] ?? 0);
                        }
                    }
                    $openAiInfo['audio_seconds_month'] = $seconds;
                }
            } catch (\Throwable $e) {
                // Non-fatal
            }
        }
        // Also mark last AI failure (from storage/logs) to flag quota issues.
        try {
            $fail = \App\Models\JokeCall::where('failure_reason', 'like', 'IA no disponible%')
                ->latest()->first();
            if ($fail) {
                $openAiInfo['last_ai_failure_at'] = $fail->updated_at?->toIso8601String();
                $openAiInfo['last_ai_failure_reason'] = $fail->failure_reason;
            }
        } catch (\Throwable $e) {}

        // Anthropic & api-ninjas don't expose public balance endpoints; keep
        // presence + basic config so the card shows "configured".
        $anthropicConfigured = !empty(config('services.anthropic.api_key'));
        $apiNinjasConfigured = !empty(config('services.api_ninjas.key'));

        // ElevenLabs usage
        $elevenLabsInfo = null;
        try {
            $elResponse = \Illuminate\Support\Facades\Http::withHeaders([
                'xi-api-key' => config('services.elevenlabs.api_key', env('ELEVENLABS_API_KEY')),
            ])->get('https://api.elevenlabs.io/v1/user/subscription');
            if ($elResponse->ok()) {
                $elData = $elResponse->json();
                $elevenLabsInfo = [
                    'characters_used' => $elData['character_count'] ?? 0,
                    'characters_limit' => $elData['character_limit'] ?? 0,
                    'characters_remaining' => ($elData['character_limit'] ?? 0) - ($elData['character_count'] ?? 0),
                    'tier' => $elData['tier'] ?? 'unknown',
                ];
            }
        } catch (\Throwable $e) {
            $elevenLabsInfo = ['error' => $e->getMessage()];
        }

        return response()->json([
            'twilio' => $twilioBalance,
            'elevenlabs' => $elevenLabsInfo,
            'openai' => $openAiInfo,
            'anthropic' => ['configured' => $anthropicConfigured],
            'api_ninjas' => ['configured' => $apiNinjasConfigured],
            'calls' => [
                'total' => $totalCalls,
                'admin' => $adminCalls,
                'trial' => $trialCalls,
                'paid' => $paidCalls,
                'today' => $callsToday,
                'this_month' => $callsThisMonth,
                'completed_today' => $completedToday,
                'completed_month' => $completedMonth,
            ],
            'minutes' => [
                'total' => round($totalMinutes, 1),
                'this_month' => round($monthMinutes, 1),
            ],
            'costs' => [
                'estimated_month_usd' => $estimatedCostMonth,
                'estimated_total_usd' => $estimatedCostTotal,
                'cost_per_minute_usd' => $costPerMinute,
            ],
            'revenue_usd' => $revenue,
            'revenue_mxn' => round($revenueMxn, 2),
            'revenue_net_mxn' => round($revenueNet, 2),
        ]);
    }

    public function userDetail(User $user): JsonResponse
    {
        $credit = \App\Models\UserCredit::where('user_id', $user->id)->first();
        $calls = JokeCall::where('user_id', $user->id)->latest()->take(20)->get();
        $trialCalls = JokeCall::where('ip_address', request()->ip()) // approximate
            ->where('joke_source', 'trial')->count();

        // Stripe customer info
        $stripeInfo = null;
        if ($user->stripe_customer_id && config('services.stripe.secret')) {
            try {
                \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                $customer = \Stripe\Customer::retrieve($user->stripe_customer_id);
                $paymentMethods = \Stripe\PaymentMethod::all([
                    'customer' => $user->stripe_customer_id,
                    'type' => 'card',
                ]);
                $cards = [];
                foreach ($paymentMethods->data as $pm) {
                    $cards[] = [
                        'brand' => $pm->card->brand,
                        'last4' => $pm->card->last4,
                        'exp_month' => $pm->card->exp_month,
                        'exp_year' => $pm->card->exp_year,
                    ];
                }
                $stripeInfo = [
                    'customer_id' => $customer->id,
                    'email' => $customer->email,
                    'cards' => $cards,
                ];
            } catch (\Throwable $e) {
                $stripeInfo = ['error' => $e->getMessage()];
            }
        }

        return response()->json([
            'user' => $user,
            'credits' => $credit?->credits_remaining ?? 0,
            'jokes' => $credit?->jokes_remaining ?? 0,
            'jokes_reset_at' => $credit?->jokes_reset_at,
            'calls' => $calls,
            'call_stats' => [
                'total' => JokeCall::where('user_id', $user->id)->count(),
                'completed' => JokeCall::where('user_id', $user->id)->where('status', JokeCallStatus::Completed)->count(),
                'paid' => JokeCall::where('user_id', $user->id)->where('joke_source', 'paid')->count(),
            ],
            'stripe' => $stripeInfo,
        ]);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'credits' => 'nullable|integer|min:0',
            'jokes' => 'nullable|integer|min:0',
            'subscription_plan' => 'nullable|string',
            'is_admin' => 'nullable|boolean',
        ]);

        if (isset($data['credits']) || isset($data['jokes'])) {
            $credit = \App\Models\UserCredit::firstOrCreate(
                ['user_id' => $user->id],
                ['credits_remaining' => 0, 'jokes_remaining' => 0, 'jokes_reset_at' => now()->addMonth()]
            );
            $patch = [];
            if (isset($data['credits'])) $patch['credits_remaining'] = $data['credits'];
            if (isset($data['jokes'])) {
                $patch['jokes_remaining'] = $data['jokes'];
                $patch['jokes_reset_at'] = now()->addMonth();
            }
            $credit->update($patch);
        }

        if (array_key_exists('subscription_plan', $data)) {
            $user->update(['subscription_plan' => $data['subscription_plan']]);
        }

        if (isset($data['is_admin'])) {
            $user->update(['is_admin' => $data['is_admin']]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Hard-delete a user and every record tied to them (credits, calls,
     * referrals). Admin-only. Cannot self-delete, cannot delete another admin.
     */
    /**
     * Manually end a live call. Used from the admin call-detail page when
     * something goes off the rails (AI loop, victim getting upset, etc.).
     * Calls Twilio to force status=completed and marks the row accordingly.
     */
    public function hangupCall(JokeCall $jokeCall): JsonResponse
    {
        if (!$jokeCall->twilio_call_sid) {
            return response()->json(['error' => 'Esta llamada no tiene Twilio SID.'], 400);
        }
        if ($jokeCall->status->isTerminal()) {
            return response()->json(['error' => 'La llamada ya terminó.'], 400);
        }

        try {
            $twilio = new \Twilio\Rest\Client(config('services.twilio.sid'), config('services.twilio.auth_token'));
            $twilio->calls($jokeCall->twilio_call_sid)->update(['status' => 'completed']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Admin hangup failed', [
                'call_id' => $jokeCall->id,
                'sid' => $jokeCall->twilio_call_sid,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'No se pudo colgar: ' . $e->getMessage()], 500);
        }

        $jokeCall->update([
            'status' => JokeCallStatus::Completed,
            'failure_reason' => 'Colgada manualmente desde el panel',
        ]);

        \Illuminate\Support\Facades\Log::info('Admin hung up call', [
            'call_id' => $jokeCall->id,
            'sid' => $jokeCall->twilio_call_sid,
        ]);

        return response()->json(['ok' => true]);
    }

    public function destroyUser(Request $request, User $user): JsonResponse
    {
        $currentAdmin = $request->user();
        if ($user->id === $currentAdmin->id) {
            return response()->json(['error' => 'No puedes eliminar tu propia cuenta.'], 400);
        }
        if ($user->is_admin) {
            return response()->json(['error' => 'No puedes eliminar otra cuenta admin desde aquí.'], 400);
        }

        $summary = \DB::transaction(function () use ($user) {
            $calls = JokeCall::where('user_id', $user->id)->count();
            JokeCall::where('user_id', $user->id)->delete();

            // user_credits + referrals + ab_test_events + visitor_touchpoints
            // clean up automatically via their FK cascade / nullOnDelete rules.
            $email = $user->email;
            $user->delete();

            return ['deleted_email' => $email, 'deleted_calls' => $calls];
        });

        \Illuminate\Support\Facades\Log::info('Admin deleted user', [
            'admin_id' => $currentAdmin->id,
            'deleted_email' => $summary['deleted_email'],
            'deleted_calls' => $summary['deleted_calls'],
        ]);

        return response()->json(['ok' => true, 'deleted' => $summary]);
    }

    public function presets(): JsonResponse
    {
        return response()->json(Preset::orderBy('sort_order')->get());
    }

    public function referrals(\Illuminate\Http\Request $request): JsonResponse
    {
        $me = $request->user();
        $myCode = $me?->referral_code;
        $myClicks = 0;
        $myUniques = 0;
        $mySignups = 0;
        $myConverted = 0;
        if ($myCode) {
            $base = '%/r/' . $myCode . '%';
            $myClicks = \Illuminate\Support\Facades\DB::table('visitor_touchpoints')->where('landing_page', 'like', $base)->count();
            $myUniques = \Illuminate\Support\Facades\DB::table('visitor_touchpoints')->where('landing_page', 'like', $base)->distinct('visitor_id')->count('visitor_id');
            $mySignups = \App\Models\User::where('referred_by_user_id', $me->id)->count();
            $myConverted = \App\Models\User::where('referred_by_user_id', $me->id)->whereNotNull('referral_credited_at')->count();
        }

        $total = \App\Models\User::count();
        $withReferrer = \App\Models\User::whereNotNull('referred_by_user_id')->count();
        $credited = \App\Models\User::whereNotNull('referral_credited_at')->count();
        $activeUsers = \App\Models\User::whereHas('jokeCalls')->count();
        $kFactor = $activeUsers > 0 ? round($credited / $activeUsers, 3) : 0;

        // Viral cycle time: avg days between referrer.created_at and referee.created_at (for credited pairs)
        $avgCycleDays = \App\Models\User::whereNotNull('users.referral_credited_at')
            ->whereNotNull('users.referred_by_user_id')
            ->join('users as ref', 'ref.id', '=', 'users.referred_by_user_id')
            ->selectRaw("AVG((julianday(users.created_at) - julianday(ref.created_at))) as avg_days")
            ->value('avg_days');

        $top = \App\Models\User::leftJoin('users as referred', 'referred.referred_by_user_id', '=', 'users.id')
            ->select('users.id', 'users.name', 'users.email', 'users.referral_code')
            ->selectRaw('COUNT(referred.id) as referred_count')
            ->selectRaw('COUNT(CASE WHEN referred.referral_credited_at IS NOT NULL THEN 1 END) as converted_count')
            ->groupBy('users.id', 'users.name', 'users.email', 'users.referral_code')
            ->having('referred_count', '>', 0)
            ->orderByDesc('converted_count')
            ->orderByDesc('referred_count')
            ->limit(25)
            ->get();

        return response()->json([
            'stats' => [
                'total_users' => $total,
                'with_referrer' => $withReferrer,
                'credited' => $credited,
                'credits_given' => $credited * 4,
                'active_users' => $activeUsers,
                'k_factor' => $kFactor,
                'avg_cycle_days' => $avgCycleDays ? round($avgCycleDays, 1) : null,
            ],
            'me' => [
                'code' => $myCode,
                'link' => $myCode ? url('/r/' . $myCode) : null,
                'clicks' => $myClicks,
                'unique_visitors' => $myUniques,
                'signups' => $mySignups,
                'converted' => $myConverted,
                'conversion_rate' => $myClicks > 0 ? round(($mySignups / $myClicks) * 100, 1) : 0,
            ],
            'top' => $top,
        ]);
    }

    public function viralMetrics(): JsonResponse
    {
        // Share funnel
        $totalCalls = \App\Models\JokeCall::count();
        $publicCalls = \App\Models\JokeCall::where('is_public', true)->count();
        $callsWithViews = \App\Models\JokeCall::where('share_views', '>', 0)->count();
        $totalShareViews = (int) \App\Models\JokeCall::sum('share_views');
        $shareRate = $totalCalls > 0 ? round(($callsWithViews / $totalCalls) * 100, 1) : 0;

        // UTM attribution
        $channels = \Illuminate\Support\Facades\DB::table('visitor_touchpoints')
            ->select('utm_source', \Illuminate\Support\Facades\DB::raw('COUNT(*) as touches'), \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT user_id) as users'))
            ->whereNotNull('utm_source')
            ->groupBy('utm_source')
            ->orderByDesc('touches')
            ->get();

        // A/B test: WhatsApp variant CTR
        $wa = \Illuminate\Support\Facades\DB::table('ab_test_events')
            ->where('test_name', 'whatsapp_share_caption')
            ->select('variant', 'event_type', \Illuminate\Support\Facades\DB::raw('COUNT(*) as n'))
            ->groupBy('variant', 'event_type')
            ->get()
            ->groupBy('variant')
            ->map(function ($events, $variant) {
                $imp = (int) ($events->firstWhere('event_type', 'impression')?->n ?? 0);
                $clk = (int) ($events->firstWhere('event_type', 'click')?->n ?? 0);
                return [
                    'variant' => $variant,
                    'impressions' => $imp,
                    'clicks' => $clk,
                    'ctr' => $imp > 0 ? round(($clk / $imp) * 100, 1) : 0,
                ];
            })->values();

        return response()->json([
            'share_funnel' => [
                'total_calls' => $totalCalls,
                'public_calls' => $publicCalls,
                'calls_with_views' => $callsWithViews,
                'total_share_views' => $totalShareViews,
                'share_rate' => $shareRate,
            ],
            'channels' => $channels,
            'whatsapp_ab' => $wa,
        ]);
    }

    public function createPreset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label' => 'required|string|max:100',
            'emoji' => 'required|string|max:10',
            'scenario' => 'required|string',
            'character' => 'nullable|string|max:200',
            'voice' => 'required|in:ash,coral',
            'category' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        return response()->json(Preset::create($data), 201);
    }

    public function updatePreset(Request $request, Preset $preset): JsonResponse
    {
        $data = $request->validate([
            'label' => 'string|max:100',
            'emoji' => 'string|max:10',
            'scenario' => 'string',
            'character' => 'nullable|string|max:200',
            'voice' => 'in:ash,coral',
            'category' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $preset->update($data);
        return response()->json($preset);
    }

    public function deletePreset(Preset $preset): JsonResponse
    {
        $preset->delete();
        return response()->json(['ok' => true]);
    }
}
