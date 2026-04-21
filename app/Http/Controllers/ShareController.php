<?php

namespace App\Http\Controllers;

use App\Models\JokeCall;
use Illuminate\Http\Request;

class ShareController extends Controller
{
    public function show(string $sessionId, Request $request)
    {
        $jokeCall = JokeCall::where('session_id', $sessionId)->firstOrFail();

        $audioUrl = $jokeCall->recording_url ? route('share.audio', $jokeCall->session_id) : null;

        if ($request->wantsJson()) {
            $transcript = $jokeCall->ai_transcript ?? [];

            return response()->json([
                'scenario' => $jokeCall->custom_joke_prompt,
                'joke_text' => $jokeCall->joke_text,
                'recording_url' => $audioUrl,
                'conversation' => $transcript['conversation'] ?? [],
                'call_duration_seconds' => $jokeCall->call_duration_seconds,
                'created_at' => $jokeCall->created_at->toIso8601String(),
            ]);
        }

        return view('share', ['jokeCall' => $jokeCall, 'audioUrl' => $audioUrl]);
    }
}
