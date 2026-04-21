<?php

namespace App\Http\Controllers;

use App\Models\JokeCall;
use Illuminate\Http\Request;

class ShareController extends Controller
{
    public function show(string $sessionId, Request $request)
    {
        $jokeCall = JokeCall::where('session_id', $sessionId)->firstOrFail();
        // Back-compat: old /share/{sessionId} redirects to /v/{slug}
        if ($jokeCall->share_slug && !$request->wantsJson()) {
            return redirect()->route('share.v', ['slug' => $jokeCall->share_slug], 301);
        }
        return $this->render($jokeCall, $request);
    }

    public function showBySlug(string $slug, Request $request)
    {
        $jokeCall = JokeCall::where('share_slug', $slug)->firstOrFail();
        if (!$jokeCall->is_public && !$request->wantsJson()) abort(404);

        // Track a view (not JSON, not bot)
        if (!$request->wantsJson()) {
            $ua = strtolower((string) $request->userAgent());
            if (!str_contains($ua, 'bot') && !str_contains($ua, 'crawler') && !str_contains($ua, 'spider')) {
                $jokeCall->increment('share_views');
            }
        }

        return $this->render($jokeCall, $request);
    }

    private function render(JokeCall $jokeCall, Request $request)
    {
        $audioUrl = $jokeCall->recording_url ? route('share.audio', $jokeCall->session_id) : null;
        $creator = $jokeCall->user_id ? \App\Models\User::find($jokeCall->user_id) : null;

        if ($request->wantsJson()) {
            $transcript = $jokeCall->ai_transcript ?? [];
            return response()->json([
                'scenario' => $jokeCall->custom_joke_prompt,
                'joke_text' => $jokeCall->joke_text,
                'recording_url' => $audioUrl,
                'victim_name' => $jokeCall->victim_name,
                'creator_name' => $creator?->name,
                'transcript' => $jokeCall->live_transcript ? json_decode($jokeCall->live_transcript, true) : [],
                'conversation' => $transcript['conversation'] ?? [],
                'call_duration_seconds' => $jokeCall->call_duration_seconds,
                'share_views' => $jokeCall->share_views,
                'slug' => $jokeCall->share_slug,
                'created_at' => $jokeCall->created_at->toIso8601String(),
            ]);
        }

        return view('share', [
            'jokeCall' => $jokeCall,
            'audioUrl' => $audioUrl,
            'creatorName' => $creator?->name,
        ]);
    }
}
