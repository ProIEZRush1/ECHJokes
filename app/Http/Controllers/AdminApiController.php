<?php

namespace App\Http\Controllers;

use App\Enums\JokeCallStatus;
use App\Models\JokeCall;
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
}
