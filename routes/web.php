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
Route::get('/dashboard', fn() => view('app'))->name('dashboard');

// Auth (magic link)
Route::post('/auth/magic-link', [AuthController::class, 'sendMagicLink'])->name('auth.magic-link');
Route::get('/auth/verify/{user}', [AuthController::class, 'verifyMagicLink'])->name('auth.verify');
Route::get('/api/user', [AuthController::class, 'user'])->name('auth.user');
Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

// API
Route::post('/checkout', [ECHJokesController::class, 'createCheckout'])
    ->middleware('throttle:5,60')
    ->name('checkout');

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
