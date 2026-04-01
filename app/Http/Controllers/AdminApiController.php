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
        $query = JokeCall::query()->latest();

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
            'character' => 'required|string',
            'voice' => 'required|in:ash,coral',
        ]);

        $phone = $request->input('phone_number');
        if (!str_starts_with($phone, '+')) {
            $phone = '+52' . $phone;
        }

        $jokeCall = JokeCall::create([
            'session_id' => Str::ulid()->toBase32(),
            'phone_number' => $phone,
            'joke_category' => 'prank',
            'joke_source' => 'custom',
            'custom_joke_prompt' => $request->input('scenario'),
            'delivery_type' => 'call',
            'status' => JokeCallStatus::Calling,
            'ip_address' => $request->ip(),
        ]);

        try {
            $twilio = new \Twilio\Rest\Client(
                config('services.twilio.sid'),
                config('services.twilio.auth_token')
            );

            $call = $twilio->calls->create($phone, config('services.twilio.phone_number'), [
                'url' => url('/conversation/start') . '?scenario=' . urlencode($request->input('scenario')) . '&character=' . urlencode($request->input('character')) . '&voice=' . urlencode($request->input('voice')),
                'method' => 'POST',
                'statusCallback' => route('twilio.status'),
                'statusCallbackEvent' => ['initiated', 'ringing', 'answered', 'completed'],
                'statusCallbackMethod' => 'POST',
                'machineDetection' => 'Enable',
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

        // Estimated costs (rough: $0.35/min for Twilio+OpenAI)
        $costPerMinute = 0.35;
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

        return response()->json([
            'twilio' => $twilioBalance,
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

    public function presets(): JsonResponse
    {
        return response()->json(Preset::orderBy('sort_order')->get());
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
