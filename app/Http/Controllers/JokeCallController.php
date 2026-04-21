<?php

namespace App\Http\Controllers;

use App\Enums\JokeCallStatus;
use App\Models\JokeCall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class JokeCallController extends Controller
{
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
        $user = auth()->user();

        // Quota enforcement (admin bypasses)
        if ($user && !$user->is_admin) {
            $credit = \App\Models\UserCredit::firstOrCreate(['user_id' => $user->id], [
                'credits_remaining' => 0, 'jokes_remaining' => 5, 'jokes_reset_at' => now()->addMonth(),
            ]);
            if (!$credit->consumeJoke()) {
                return response()->json(['error' => 'Ya usaste tus chistes de este mes. Compra un plan para más.'], 429);
            }
        } elseif (!$user) {
            // Anonymous: 1 joke/day per destination number
            $todayCount = JokeCall::where('phone_number', $phone)
                ->where('delivery_type', 'joke_call')
                ->whereNull('user_id')
                ->where('created_at', '>', now()->subDay())
                ->count();
            if ($todayCount >= 1) {
                return response()->json(['error' => 'Ya se envió un chiste a este número hoy. Regístrate gratis para 5 chistes al mes.'], 429);
            }
        }

        $joke = $this->fetchJoke($lang);
        if (!$joke) {
            return response()->json(['error' => 'No se pudo obtener un chiste'], 500);
        }

        $jokeText = $joke['type'] === 'single' ? $joke['joke'] : $joke['setup'] . ' ... ' . $joke['delivery'];

        // Generate audio via ElevenLabs
        $audioPath = $this->generateAudio($jokeText, $lang);
        if (!$audioPath) {
            return response()->json(['error' => 'No se pudo generar el audio'], 500);
        }

        $jokeCall = JokeCall::create([
            'session_id' => Str::ulid()->toBase32(),
            'phone_number' => $phone,
            'joke_category' => $joke['category'] ?? 'Any',
            'joke_source' => $source,
            'joke_text' => $jokeText,
            'custom_joke_prompt' => $jokeText,
            'delivery_type' => 'joke_call',
            'voice' => $lang,
            'audio_file_path' => $audioPath,
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
        $audioPath = $jokeCall->audio_file_path;

        if ($audioPath && Storage::exists($audioPath)) {
            // Play ElevenLabs pre-generated audio
            $audioUrl = url('/joke/audio/' . $jokeCall->id);
            $twiml = '<Pause length="2"/><Play>' . e($audioUrl) . '</Play><Hangup/>';
        } else {
            // Fallback to Twilio TTS
            $twiml = '<Pause length="2"/><Say language="es-MX" voice="Polly.Mia">' . e($this->clean($jokeCall->joke_text ?? '')) . '</Say><Hangup/>';
        }

        return response('<?xml version="1.0" encoding="UTF-8"?><Response>' . $twiml . '</Response>', 200, ['Content-Type' => 'text/xml']);
    }

    public function serveAudio(JokeCall $jokeCall): \Symfony\Component\HttpFoundation\Response
    {
        $path = $jokeCall->audio_file_path;
        if (!$path || !Storage::exists($path)) {
            abort(404);
        }
        return response(Storage::get($path), 200, [
            'Content-Type' => 'audio/mpeg',
            'Content-Length' => Storage::size($path),
        ]);
    }

    public function punchline(JokeCall $jokeCall): Response
    {
        // Not used with ElevenLabs (full joke in one audio), kept for backwards compat
        return response('<?xml version="1.0" encoding="UTF-8"?><Response><Hangup/></Response>', 200, ['Content-Type' => 'text/xml']);
    }

    private function pickVoiceId(): string
    {
        $pool = array_filter(array_map('trim', explode(',', env('ELEVENLABS_VOICES_MALE', ''))));
        if (empty($pool)) {
            $pool = [env('ELEVENLABS_VOICE_ID', 'iP95p4xoKVk53GoZ742B')];
        }
        return $pool[array_rand($pool)];
    }

    private function wrapJoke(string $joke, string $lang): string
    {
        if ($lang !== 'es') return $joke;
        $openers = ['Oye, qué tal, fíjate que te tengo un chiste bien bueno... ', 'Ey, hola, aguántame tantito, te voy a contar un chistorete... ', 'Bueno, oye, ya que agarré el teléfono, chécate este chiste... ', 'Ajá, mira, no es por nada pero me acordé de un chiste, va... ', 'Hola, este, te hablo porque me acordé de un chiste bien chistoso, escucha... '];
        $closers = [' ... Jaja, no manches, ¿verdad? Bueno, ya, adiós!', ' ... Ay, no, qué risa. Bueno, pos ya, nos vemos!', ' ... Jajaja, ¿tá bueno, eh? Órale, cuídate!', ' ... Ay wey, qué chistoso. Bueno, hasta luego!', ' ... Jaja, chale. Bueno, ya, bye!'];
        return $openers[array_rand($openers)] . $joke . $closers[array_rand($closers)];
    }

    private function generateAudio(string $text, string $lang): ?string
    {
        $apiKey = config('services.elevenlabs.api_key', env('ELEVENLABS_API_KEY'));
        $voiceId = $this->pickVoiceId();
        $wrapped = $this->wrapJoke($text, $lang);

        if (!$apiKey) return null;

        try {
            $response = Http::withHeaders([
                'xi-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}?output_format=mp3_44100_128", [
                'text' => $wrapped,
                'model_id' => 'eleven_turbo_v2_5',
                'voice_settings' => ['stability' => 0.35, 'similarity_boost' => 0.75, 'style' => 0.45, 'use_speaker_boost' => true],
            ]);

            if ($response->ok()) {
                $filename = 'jokes/' . Str::ulid() . '.mp3';
                Storage::put($filename, $response->body());
                Log::info('Joke TTS generated', ['voice_id' => $voiceId, 'lang' => $lang]);
                return $filename;
            }
        } catch (\Throwable $e) {
            Log::warning('ElevenLabs joke TTS failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Primary joke source: api-ninjas.com/api/jokes (English). For any other
     * language we translate the fetched joke via Claude Haiku and cache both
     * versions so the upstream rate-limit falls back to a random local joke
     * in the requested language.
     */
    private function fetchJoke(string $lang): ?array
    {
        $lang = $lang ?: 'es';
        $apiKey = config('services.api_ninjas.key');

        // 1. Fetch + cache an English joke from api-ninjas.
        $englishJoke = null;
        if ($apiKey) {
            try {
                $resp = Http::withHeaders(['X-Api-Key' => $apiKey])
                    ->timeout(5)
                    ->get('https://api.api-ninjas.com/v1/jokes');
                if ($resp->ok() && is_array($resp->json()) && !isset($resp->json()['error'])) {
                    foreach ($resp->json() as $row) {
                        $text = trim((string) ($row['joke'] ?? ''));
                        if ($text === '') continue;
                        \App\Models\JokeCache::firstOrCreate(
                            ['joke_hash' => hash('sha256', 'en|' . mb_strtolower($text))],
                            ['joke_text' => $text, 'language' => 'en', 'source' => 'api-ninjas', 'fetched_at' => now()]
                        );
                        $englishJoke ??= $text;
                    }
                } else {
                    Log::info('api-ninjas non-2xx', ['status' => $resp->status(), 'body' => substr($resp->body(), 0, 200)]);
                }
            } catch (\Throwable $e) {
                Log::warning('api-ninjas fetch error', ['error' => $e->getMessage()]);
            }
        }

        // 2. If caller wants English, return immediately.
        if ($lang === 'en' && $englishJoke) {
            return ['type' => 'single', 'joke' => $englishJoke, 'category' => 'any'];
        }

        // 3. For other languages: translate + cache.
        if ($englishJoke) {
            $translated = $this->translateJoke($englishJoke, $lang);
            if ($translated) {
                \App\Models\JokeCache::firstOrCreate(
                    ['joke_hash' => hash('sha256', $lang . '|' . mb_strtolower($translated))],
                    ['joke_text' => $translated, 'language' => $lang, 'source' => 'translation', 'fetched_at' => now()]
                );
                return ['type' => 'single', 'joke' => $translated, 'category' => 'any'];
            }
        }

        // 4. Fallback: random cached joke in the requested language, then any.
        $cached = \App\Models\JokeCache::where('language', $lang)->orderByRaw('use_count ASC, RANDOM()')->first()
            ?: \App\Models\JokeCache::orderByRaw('use_count ASC, RANDOM()')->first();
        if ($cached) {
            $cached->increment('use_count');
            // If the cached one isn't in the requested language, try to translate it.
            if ($cached->language !== $lang) {
                $translated = $this->translateJoke($cached->joke_text, $lang);
                if ($translated) {
                    \App\Models\JokeCache::firstOrCreate(
                        ['joke_hash' => hash('sha256', $lang . '|' . mb_strtolower($translated))],
                        ['joke_text' => $translated, 'language' => $lang, 'source' => 'translation', 'fetched_at' => now()]
                    );
                    return ['type' => 'single', 'joke' => $translated, 'category' => 'cached'];
                }
            }
            return ['type' => 'single', 'joke' => $cached->joke_text, 'category' => 'cached'];
        }

        return null;
    }

    private function translateJoke(string $text, string $lang): ?string
    {
        $key = config('services.anthropic.api_key');
        if (!$key) return null;
        $langNames = [
            'es' => 'Mexican Spanish (casual, natural, as told by a friend)',
            'pt' => 'Brazilian Portuguese (casual)',
            'fr' => 'French (casual)',
            'de' => 'German (casual)',
            'en' => 'English',
        ];
        $langName = $langNames[$lang] ?? $lang;
        try {
            $resp = Http::timeout(8)->withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-haiku-4-5-20251001',
                'max_tokens' => 300,
                'temperature' => 0.7,
                'system' => "Translate the joke I give you to {$langName}. Keep it funny — if the pun doesn't translate, rewrite it with an equivalent pun that lands in the target language. Return ONLY the translated joke, no quotes, no explanation.",
                'messages' => [['role' => 'user', 'content' => $text]],
            ]);
            $out = trim((string) $resp->json('content.0.text'));
            return $out !== '' ? $out : null;
        } catch (\Throwable $e) {
            Log::warning('Joke translation failed', ['error' => $e->getMessage(), 'lang' => $lang]);
            return null;
        }
    }

    private function clean(string $text): string
    {
        return str_replace(['<', '>', '&', '"', "'"], ['', '', 'y', '', ''], trim($text));
    }
}
