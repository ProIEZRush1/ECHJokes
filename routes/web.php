<?php

use App\Http\Controllers\AudioController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ECHJokesController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TwilioWebhookController;
use App\Http\Middleware\VerifyTwilioSignature;
use Illuminate\Support\Facades\Route;

// App pages (Vue SPA) — catch-all for client-side routing
Route::get('/', [ECHJokesController::class, 'index'])->name('home');
Route::get('/call/{jokeCall}/status', [ECHJokesController::class, 'callStatus'])->name('call.status');

// Share page (public, with OG meta tags)
Route::get('/share/{sessionId}', [ShareController::class, 'show'])->name('share.show');

// SPA catch-all routes (Vue Router handles these)
Route::get('/pricing', fn() => view('app'))->name('pricing');
Route::get('/login', fn() => view('app'))->name('login');
Route::get('/dashboard/{any?}', fn() => view('app'))->where('any', '.*')->name('dashboard');

// Auth (magic link)
Route::post('/auth/magic-link', [AuthController::class, 'sendMagicLink'])->name('auth.magic-link');
Route::get('/auth/verify/{user}', [AuthController::class, 'verifyMagicLink'])->name('auth.verify');
Route::get('/api/user', [AuthController::class, 'user'])->name('auth.user');
Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

// API
Route::post('/checkout', [ECHJokesController::class, 'createCheckout'])
    ->middleware('throttle:5,60')
    ->name('checkout');

Route::post('/trial', [ECHJokesController::class, 'trialCall'])
    ->middleware('throttle:3,60')
    ->name('trial');

// Test mode: skip Stripe, directly process a call (local env only)
Route::post('/test/call', [ECHJokesController::class, 'testCall'])
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
Route::post('/conversation/start', [\App\Http\Controllers\ConversationWebhookController::class, 'start'])->name('conversation.start');
Route::post('/conversation/gather', [\App\Http\Controllers\ConversationWebhookController::class, 'gather'])->name('conversation.gather');
Route::get('/conversation/audio/{filename}', [\App\Http\Controllers\ConversationWebhookController::class, 'audio'])->name('conversation.audio');

// User API
Route::prefix('user-api')->group(function () {
    Route::post('/register', [\App\Http\Controllers\UserApiController::class, 'register']);
    Route::post('/login', [\App\Http\Controllers\UserApiController::class, 'login']);
    Route::post('/logout', [\App\Http\Controllers\UserApiController::class, 'logout']);
    Route::get('/plans', [\App\Http\Controllers\UserApiController::class, 'plans']);

    Route::middleware('auth')->group(function () {
        Route::get('/me', [\App\Http\Controllers\UserApiController::class, 'me']);
        Route::get('/calls', [\App\Http\Controllers\UserApiController::class, 'myCalls']);
        Route::get('/calls/{jokeCall}', [\App\Http\Controllers\UserApiController::class, 'myCall']);
        Route::post('/buy-plan', [\App\Http\Controllers\UserApiController::class, 'buyPlan']);
        Route::post('/make-call', [\App\Http\Controllers\UserApiController::class, 'makeCall']);
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
        Route::post('/launch-call', [\App\Http\Controllers\AdminApiController::class, 'launchCall']);
        Route::get('/users', [\App\Http\Controllers\AdminApiController::class, 'users']);
        Route::get('/billing', [\App\Http\Controllers\AdminApiController::class, 'billing']);
        Route::get('/plans', [\App\Http\Controllers\AdminApiController::class, 'plans']);
        Route::post('/plans', [\App\Http\Controllers\AdminApiController::class, 'createPlan']);
        Route::put('/plans/{plan}', [\App\Http\Controllers\AdminApiController::class, 'updatePlan']);
        Route::delete('/plans/{plan}', [\App\Http\Controllers\AdminApiController::class, 'deletePlan']);
    });
});

// Admin SPA catch-all (Vue handles routing)
Route::get('/admin/{any?}', fn() => view('app'))->where('any', '.*')->name('admin');

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
