<?php

use App\Http\Controllers\AudioController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\VaciladaController;
use App\Http\Controllers\PresetPageController;
use App\Http\Controllers\AbTestController;
use App\Http\Controllers\OgImageController;
use App\Http\Controllers\ReferralLandingController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\SharedAudioController;
use App\Http\Controllers\TrendingController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TwilioWebhookController;
use App\Http\Middleware\VerifyTwilioSignature;
use Illuminate\Support\Facades\Route;

// App pages (Vue SPA) — catch-all for client-side routing
Route::get('/', [VaciladaController::class, 'index'])->name('home');
Route::get('/call/{jokeCall}/status', [VaciladaController::class, 'callStatus'])->name('call.status');

// Share page (public, with OG meta tags)
Route::get('/share/{sessionId}', [ShareController::class, 'show'])->name('share.show');
Route::get('/share/{sessionId}/audio.mp3', [SharedAudioController::class, 'stream'])->name('share.audio');

// Public call page by short slug (preferred, from /v/{slug})
Route::get('/v/{slug}', [ShareController::class, 'showBySlug'])->name('share.v');
Route::get('/v/{slug}/og.svg', [OgImageController::class, 'forCall'])->name('share.v.og');

// Trending feed
Route::get('/trending', [TrendingController::class, 'index'])->name('trending');

// Referrer landing page
Route::get('/r/{code}', [ReferralLandingController::class, 'show'])->name('referral.landing');

// A/B test event logging
Route::post('/api/ab/event', [AbTestController::class, 'event'])->name('ab.event');

// SEO — preset landing pages + sitemap
Route::get('/bromas', [PresetPageController::class, 'index'])->name('presets.index');
Route::get('/bromas/{preset:slug}', [PresetPageController::class, 'show'])->name('presets.show');
Route::get('/sitemap.xml', [PresetPageController::class, 'sitemap'])->name('sitemap');

// Press kit (public marketing/press page)
Route::get('/press', fn() => view('press'))->name('press');

// SPA catch-all routes (Vue Router handles these)
Route::get('/pricing', fn() => view('app'))->name('pricing');
Route::get('/login', fn() => view('app'))->name('login');
Route::get('/terms', fn() => view('app'))->name('terms');
Route::get('/privacy', fn() => view('app'))->name('privacy');
Route::get('/dashboard/{any?}', fn() => view('app'))->where('any', '.*')->name('dashboard');

// Auth (magic link)
Route::post('/auth/magic-link', [AuthController::class, 'sendMagicLink'])->name('auth.magic-link');
Route::get('/auth/verify/{user}', [AuthController::class, 'verifyMagicLink'])->name('auth.verify');
Route::get('/api/user', [AuthController::class, 'user'])->name('auth.user');
Route::get('/api/referrals/me', [AuthController::class, 'referralInfo'])->name('referrals.me');
Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

// API
Route::post('/checkout', [VaciladaController::class, 'createCheckout'])
    ->middleware('throttle:5,60')
    ->name('checkout');

Route::get('/api/presets', fn() => response()->json(
    \App\Models\Preset::where('is_active', true)->orderBy('sort_order')->get(['id', 'label', 'emoji', 'scenario', 'character', 'voice', 'style', 'category'])
));

Route::post('/api/generate-style', function (\Illuminate\Http\Request $request) {
    $request->validate(['scenario' => 'required|string|min:10|max:500']);
    $scenario = strip_tags($request->input('scenario'));

    try {
        $r = \Illuminate\Support\Facades\Http::withHeaders([
            'x-api-key' => config('services.anthropic.api_key'),
            'anthropic-version' => '2023-06-01',
        ])->timeout(8)->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-haiku-4-5-20251001',
            'max_tokens' => 60,
            'temperature' => 0.7,
            'system' => 'Basado en el escenario de broma telefonica, genera:
1. Un estilo de voz corto (max 15 palabras)
2. La voz ideal de esta lista: ash (hombre casual), ballad (hombre autoritario), coral (mujer amigable), sage (mujer profesional), shimmer (mujer energetica), verse (hombre versatil), echo (hombre joven)

Responde EXCLUSIVAMENTE con el objeto JSON sin comillas invertidas, sin marcadores de codigo, sin texto antes ni despues. Solo:
{"style":"Formal y serio con tono de autoridad","voice":"ballad","gender":"hombre"}',
            'messages' => [['role' => 'user', 'content' => $scenario]],
        ]);
        $text = trim($r->json('content.0.text') ?? '{}');
        // Claude sometimes wraps JSON in ```json fences or prefixes with text —
        // pull the first {...} block out before decoding.
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $parsed = json_decode($m[0], true);
            if ($parsed && isset($parsed['style'])) {
                return response()->json($parsed);
            }
        }
        // Final fallback: strip ticks/language hint + curly braces and use raw.
        $clean = trim(preg_replace('/```(?:json)?|```/', '', $text));
        return response()->json(['style' => $clean ?: 'Casual y natural', 'voice' => 'ash', 'gender' => 'hombre']);
    } catch (\Throwable $e) {
        return response()->json(['style' => ''], 200);
    }
})->middleware('throttle:10,1')->name('generate.style');

Route::post('/trial-joke', [\App\Http\Controllers\JokeCallController::class, 'launch'])
    ->middleware('throttle:5,60');

Route::post('/trial', [VaciladaController::class, 'trialCall'])
    ->middleware('throttle:3,60')
    ->name('trial');

// Test mode: skip Stripe, directly process a call (local env only)
Route::post('/test/call', [VaciladaController::class, 'testCall'])
    ->name('test.call');

// Audio (signed URL)
Route::get('/audio/{jokeCall}', [AudioController::class, 'show'])->name('audio.show');

// Webhooks (CSRF excluded via bootstrap/app.php)
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');

Route::prefix('webhooks/twilio')->middleware(VerifyTwilioSignature::class)->group(function () {
    Route::post('/voice/{jokeCall}', [TwilioWebhookController::class, 'voice'])->name('twilio.voice');
    Route::post('/status', [TwilioWebhookController::class, 'status'])->name('twilio.status');
    Route::post('/recording', [TwilioWebhookController::class, 'recording'])->name('twilio.recording');
});

// Conversation webhooks (Twilio Gather-based real-time AI conversation)
// Conversation webhooks — no DB dependency, state via query params
Route::post('/inbound', [\App\Http\Controllers\InboundCallController::class, 'handle'])->name('inbound');

// Joke calls (TTS only, no AI)
Route::post('/joke/twiml/{jokeCall}', [\App\Http\Controllers\JokeCallController::class, 'twiml'])->name('joke.twiml');
Route::post('/joke/punchline/{jokeCall}', [\App\Http\Controllers\JokeCallController::class, 'punchline'])->name('joke.punchline');
Route::get('/joke/audio/{jokeCall}', [\App\Http\Controllers\JokeCallController::class, 'serveAudio'])->name('joke.audio');
Route::post('/conversation/start', [\App\Http\Controllers\ConversationWebhookController::class, 'start'])->name('conversation.start');
Route::post('/conversation/gather', [\App\Http\Controllers\ConversationWebhookController::class, 'gather'])->name('conversation.gather');
Route::get('/conversation/audio/{filename}', [\App\Http\Controllers\ConversationWebhookController::class, 'audio'])->name('conversation.audio');

// User API
Route::prefix('user-api')->group(function () {
    Route::post('/register', [\App\Http\Controllers\UserApiController::class, 'register']);
    Route::post('/verify-otp', [\App\Http\Controllers\UserApiController::class, 'verifyOtp']);
    Route::post('/resend-otp', [\App\Http\Controllers\UserApiController::class, 'resendOtp']);
    Route::post('/login', [\App\Http\Controllers\UserApiController::class, 'login']);
    Route::post('/logout', [\App\Http\Controllers\UserApiController::class, 'logout']);
    Route::get('/plans', [\App\Http\Controllers\UserApiController::class, 'plans']);

    Route::middleware('auth')->group(function () {
        Route::get('/me', [\App\Http\Controllers\UserApiController::class, 'me']);
        Route::get('/calls', [\App\Http\Controllers\UserApiController::class, 'myCalls']);
        Route::get('/calls/{jokeCall}', [\App\Http\Controllers\UserApiController::class, 'myCall']);
        Route::post('/buy-plan', [\App\Http\Controllers\UserApiController::class, 'buyPlan']);
        Route::post('/buy-custom', [\App\Http\Controllers\UserApiController::class, 'buyCustom']);
        Route::post('/make-call', [\App\Http\Controllers\UserApiController::class, 'makeCall']);
        Route::post('/joke-call', [\App\Http\Controllers\JokeCallController::class, 'launch']);
    });
});

// Admin API
Route::prefix('admin-api')->group(function () {
    Route::post('/login', [\App\Http\Controllers\AdminApiController::class, 'login']);
    Route::post('/logout', [\App\Http\Controllers\AdminApiController::class, 'logout']);

    Route::middleware('auth')->group(function () {
        Route::get('/me', [\App\Http\Controllers\AdminApiController::class, 'me']);
        Route::get('/stats', [\App\Http\Controllers\AdminApiController::class, 'stats']);
        Route::get('/calls', [\App\Http\Controllers\AdminApiController::class, 'calls']);
        Route::get('/calls/{jokeCall}', [\App\Http\Controllers\AdminApiController::class, 'call']);
        Route::post('/calls/{jokeCall}/hangup', [\App\Http\Controllers\AdminApiController::class, 'hangupCall']);
        Route::post('/launch-call', [\App\Http\Controllers\AdminApiController::class, 'launchCall']);
        Route::get('/users', [\App\Http\Controllers\AdminApiController::class, 'users']);
        Route::get('/users/{user}', [\App\Http\Controllers\AdminApiController::class, 'userDetail']);
        Route::put('/users/{user}', [\App\Http\Controllers\AdminApiController::class, 'updateUser']);
        Route::delete('/users/{user}', [\App\Http\Controllers\AdminApiController::class, 'destroyUser']);
        Route::get('/billing', [\App\Http\Controllers\AdminApiController::class, 'billing']);
        Route::post('/joke-call', [\App\Http\Controllers\JokeCallController::class, 'launch']);
        Route::get('/presets', [\App\Http\Controllers\AdminApiController::class, 'presets']);
        Route::post('/presets', [\App\Http\Controllers\AdminApiController::class, 'createPreset']);
        Route::put('/presets/{preset}', [\App\Http\Controllers\AdminApiController::class, 'updatePreset']);
        Route::delete('/presets/{preset}', [\App\Http\Controllers\AdminApiController::class, 'deletePreset']);
        Route::get('/plans', [\App\Http\Controllers\AdminApiController::class, 'plans']);
        Route::post('/plans', [\App\Http\Controllers\AdminApiController::class, 'createPlan']);
        Route::put('/plans/{plan}', [\App\Http\Controllers\AdminApiController::class, 'updatePlan']);
        Route::delete('/plans/{plan}', [\App\Http\Controllers\AdminApiController::class, 'deletePlan']);
        Route::get('/referrals', [\App\Http\Controllers\AdminApiController::class, 'referrals']);
        Route::get('/viral-metrics', [\App\Http\Controllers\AdminApiController::class, 'viralMetrics']);
    });
});

// Admin SPA catch-all (Vue handles routing)
Route::get('/admin/{any?}', fn() => view('app'))->where('any', '.*')->name('admin');

// Internal: websocket server signals that the AI session died mid-call
// (OpenAI quota, auth error, network). We mark the call as Failed, refund
// the credit, and hang up Twilio so the caller isn't left listening to dead air.
Route::post('/api/call-ai-failed', function (\Illuminate\Http\Request $request) {
    $callSid = $request->input('call_sid');
    $reason = (string) $request->input('reason', 'ai_service_error');
    if (!$callSid) return response('OK');

    $jokeCall = \App\Models\JokeCall::where('twilio_call_sid', $callSid)->first();
    if (!$jokeCall) return response('OK');

    // If Twilio already marked this call Completed in the race, we still want
    // to overwrite to Failed (AI errored mid-call → recipient heard nothing).
    // Only skip if it's already been marked Failed or Refunded.
    if (in_array($jokeCall->status, [\App\Enums\JokeCallStatus::Failed, \App\Enums\JokeCallStatus::Refunded], true)) {
        return response('OK');
    }

    $previouslyCompleted = $jokeCall->status === \App\Enums\JokeCallStatus::Completed;

    \Illuminate\Support\Facades\Log::warning('AI session failed mid-call', [
        'call_id' => $jokeCall->id,
        'call_sid' => $callSid,
        'reason' => $reason,
        'previous_status' => $jokeCall->status->value,
    ]);

    $jokeCall->update([
        'status' => \App\Enums\JokeCallStatus::Failed,
        'failure_reason' => 'IA no disponible: ' . substr($reason, 0, 180),
    ]);

    // Refund the credit for paid calls (idempotent: only if we didn't
    // already refund in a prior webhook path).
    if ($jokeCall->joke_source === 'paid' && $jokeCall->user_id) {
        if ($credit = \App\Models\UserCredit::where('user_id', $jokeCall->user_id)->first()) {
            $credit->increment('credits_remaining');
        }
    }

    // Hang up the Twilio call so the recipient doesn't stay on dead air.
    try {
        $twilio = new \Twilio\Rest\Client(config('services.twilio.sid'), config('services.twilio.auth_token'));
        $twilio->calls($callSid)->update(['status' => 'completed']);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning('Could not hang up AI-failed call', ['error' => $e->getMessage()]);
    }

    return response('OK');
})->name('call.ai_failed');

// Live transcript API (called by websocket server)
Route::post('/api/call-transcript', function (\Illuminate\Http\Request $request) {
    $callSid = $request->input('call_sid');
    $role = $request->input('role'); // 'ai' or 'human'
    $text = $request->input('text');

    if (!$callSid || !$text) return response('OK');

    $jokeCall = \App\Models\JokeCall::where('twilio_call_sid', $callSid)->first();
    if (!$jokeCall) return response('OK');

    $transcript = $jokeCall->live_transcript ? json_decode($jokeCall->live_transcript, true) : [];
    $transcript[] = [
        'role' => $role,
        'text' => $text,
        'at' => now()->format('H:i:s'),
    ];
    $jokeCall->update(['live_transcript' => json_encode($transcript)]);

    return response('OK');
})->name('call.transcript');
