<?php

namespace App\Http\Controllers;

use App\Models\JokeCall;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class AudioController extends Controller
{
    public function show(Request $request, JokeCall $jokeCall): Response
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired signature');
        }

        if (! $jokeCall->audio_file_path || ! Storage::disk('local')->exists($jokeCall->audio_file_path)) {
            abort(404, 'Audio file not found');
        }

        $path = Storage::disk('local')->path($jokeCall->audio_file_path);

        return response()->file($path, [
            'Content-Type' => 'audio/mpeg',
        ]);
    }
}
