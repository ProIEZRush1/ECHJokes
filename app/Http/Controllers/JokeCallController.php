<?php

namespace App\Http\Controllers;

use App\Enums\JokeCallStatus;
use App\Models\JokeCall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class JokeCallController extends Controller
{
    private array $languageMap = [
        'es' => ['lang' => 'es', 'twilio_lang' => 'es-MX', 'twilio_voice' => 'Polly.Mia'],
        'en' => ['lang' => 'en', 'twilio_lang' => 'en-US', 'twilio_voice' => 'Polly.Joanna'],
        'de' => ['lang' => 'de', 'twilio_lang' => 'de-DE', 'twilio_voice' => 'Polly.Marlene'],
        'pt' => ['lang' => 'pt', 'twilio_lang' => 'pt-BR', 'twilio_voice' => 'Polly.Vitoria'],
        'fr' => ['lang' => 'fr', 'twilio_lang' => 'fr-FR', 'twilio_voice' => 'Polly.Celine'],
    ];

    public function launch(Request $request): JsonResponse
    {
        $request->validate([
            'phone_number' => 'required|string',
            'language' => 'required|in:es,en,de,pt,fr',
            'source' => 'nullable|in:admin,trial,paid',
        ]);

        $phone = $request->input('phone_number');
        if (!str_starts_with($phone, '+')) $phone = '+52' . $phone;

        $lang = $request->input('language', 'es');
        $source = $request->input('source', 'admin');

        // Fetch joke from JokeAPI
        $joke = $this->fetchJoke($lang);
        if (!$joke) {
            return response()->json(['error' => 'No se pudo obtener un chiste'], 500);
        }

        $jokeCall = JokeCall::create([
            'session_id' => Str::ulid()->toBase32(),
            'phone_number' => $phone,
            'joke_category' => $joke['category'] ?? 'Any',
            'joke_source' => $source,
            'joke_text' => $joke['type'] === 'single' ? $joke['joke'] : $joke['setup'] . "\n---\n" . $joke['delivery'],
            'delivery_type' => 'joke_call',
            'voice' => $lang,
            'status' => JokeCallStatus::Calling,
            'ip_address' => $request->ip(),
            'user_id' => auth()->id(),
        ]);

        try {
            $twilio = new \Twilio\Rest\Client(
                config('services.twilio.sid'),
                config('services.twilio.auth_token')
            );

            $call = $twilio->calls->create($phone, config('services.twilio.phone_number'), [
                'url' => route('joke.twiml', ['jokeCall' => $jokeCall->id]),
                'method' => 'POST',
                'statusCallback' => route('twilio.status'),
                'statusCallbackEvent' => ['initiated', 'ringing', 'answered', 'completed'],
                'statusCallbackMethod' => 'POST',
                'timeout' => 30,
                'record' => true,
                'recordingStatusCallback' => route('twilio.recording'),
                'recordingStatusCallbackEvent' => ['completed'],
            ]);

            $jokeCall->update(['twilio_call_sid' => $call->sid]);

            return response()->json([
                'success' => true,
                'call_id' => $jokeCall->id,
                'call_sid' => $call->sid,
                'joke' => $joke,
            ]);
        } catch (\Throwable $e) {
            $jokeCall->update(['status' => JokeCallStatus::Failed, 'failure_reason' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function twiml(JokeCall $jokeCall): Response
    {
        $jokeText = $jokeCall->joke_text ?? '';
        $lang = $jokeCall->voice ?? 'es';
        $langConfig = $this->languageMap[$lang] ?? $this->languageMap['es'];

        $parts = explode("\n---\n", $jokeText);

        if (count($parts) === 2) {
            // Two-part joke: setup, wait for reaction, then punchline
            $setup = $this->clean($parts[0]);
            $punchline = $this->clean($parts[1]);

            $twiml = '<Say language="' . $langConfig['twilio_lang'] . '" voice="' . $langConfig['twilio_voice'] . '">' . e($setup) . '</Say>';
            $twiml .= '<Gather input="speech" timeout="5" speechTimeout="auto" language="' . $langConfig['twilio_lang'] . '" action="' . e(route('joke.punchline', ['jokeCall' => $jokeCall->id])) . '" method="POST">';
            $twiml .= '<Pause length="3"/>';
            $twiml .= '</Gather>';
            // If no input, just deliver punchline anyway
            $twiml .= '<Say language="' . $langConfig['twilio_lang'] . '" voice="' . $langConfig['twilio_voice'] . '">' . e($punchline) . '</Say>';
            $twiml .= '<Pause length="1"/>';
            $twiml .= '<Say language="' . $langConfig['twilio_lang'] . '" voice="' . $langConfig['twilio_voice'] . '">Gracias por escuchar</Say>';
            $twiml .= '<Hangup/>';
        } else {
            // Single joke
            $joke = $this->clean($jokeText);
            $twiml = '<Say language="' . $langConfig['twilio_lang'] . '" voice="' . $langConfig['twilio_voice'] . '">' . e($joke) . '</Say>';
            $twiml .= '<Pause length="1"/>';
            $twiml .= '<Say language="' . $langConfig['twilio_lang'] . '" voice="' . $langConfig['twilio_voice'] . '">Gracias por escuchar</Say>';
            $twiml .= '<Hangup/>';
        }

        return response('<?xml version="1.0" encoding="UTF-8"?><Response>' . $twiml . '</Response>', 200, ['Content-Type' => 'text/xml']);
    }

    public function punchline(JokeCall $jokeCall): Response
    {
        $jokeText = $jokeCall->joke_text ?? '';
        $lang = $jokeCall->voice ?? 'es';
        $langConfig = $this->languageMap[$lang] ?? $this->languageMap['es'];

        $parts = explode("\n---\n", $jokeText);
        $punchline = $this->clean($parts[1] ?? 'jaja');

        $twiml = '<Say language="' . $langConfig['twilio_lang'] . '" voice="' . $langConfig['twilio_voice'] . '">' . e($punchline) . '</Say>';
        $twiml .= '<Pause length="1"/>';
        $twiml .= '<Say language="' . $langConfig['twilio_lang'] . '" voice="' . $langConfig['twilio_voice'] . '">Gracias por escuchar</Say>';
        $twiml .= '<Hangup/>';

        return response('<?xml version="1.0" encoding="UTF-8"?><Response>' . $twiml . '</Response>', 200, ['Content-Type' => 'text/xml']);
    }

    private function fetchJoke(string $lang): ?array
    {
        try {
            $r = Http::timeout(5)->get("https://v2.jokeapi.dev/joke/Any", [
                'lang' => $lang,
                'blacklistFlags' => 'nsfw,religious,political,racist,sexist',
                'type' => 'single,twopart',
            ]);
            if ($r->ok() && !($r->json('error'))) {
                return $r->json();
            }
        } catch (\Throwable $e) {
            Log::warning('JokeAPI failed', ['error' => $e->getMessage()]);
        }
        return null;
    }

    private function clean(string $text): string
    {
        $text = preg_replace('/[""\'\'«»]/u', '', $text);
        return str_replace(['<', '>', '&'], ['', '', 'y'], trim($text));
    }
}
