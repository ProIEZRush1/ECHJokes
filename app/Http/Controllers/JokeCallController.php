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

        // Fetch joke from JokeAPI
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
        $openers = ['Oye, que tal, fijate que te tengo un chiste bien bueno... ', 'Ey, hola, aguantame tantito, te voy a contar un chistorete... ', 'Bueno, oye, ya que agarre el telefono, checate este chiste... ', 'Aja, mira, no es por nada pero me acorde de un chiste, va... ', 'Hola, este, te hablo porque me acorde de un chiste bien chistoso, escucha... '];
        $closers = [' ... Jaja, no manches verdad? Bueno, ya, adios!', ' ... Ay, no, que risa. Bueno, pos ya, nos vemos!', ' ... Jajaja, ta bueno eh? Orale, cuidate!', ' ... Ay wey, que chistoso. Bueno, hasta luego!', ' ... Jaja, chale. Bueno ya, bye!'];
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

    private function fetchJoke(string $lang): ?array
    {
        $langNames = ['es' => 'Spanish', 'en' => 'English', 'de' => 'German', 'pt' => 'Portuguese', 'fr' => 'French'];
        $langName = $langNames[$lang] ?? 'Spanish';

        $topics = [
            'abuelitas mexicanas', 'suegras', 'el doctor', 'el mecánico', 'los tacos',
            'la tiendita de la esquina', 'el tráfico de CDMX', 'los gatos traviesos',
            'los perritos', 'el bebé de la familia', 'la vecina chismosa',
            'el albañil', 'los mariachis', 'los políticos genéricos', 'el uber',
            'el pedido de pizza', 'el calor extremo', 'la lluvia inoportuna',
            'el partido de fútbol', 'los niños en la escuela', 'la boda',
            'el gimnasio', 'el súper mercado', 'el cine', 'la playa',
            'el carnicero', 'el tianguis', 'las vacaciones', 'la aerolínea',
            'el taxista', 'los pajaritos en el parque', 'el novio celoso',
            'la quinceañera', 'el pariente lejano', 'la fiesta familiar',
            'el elote con chile', 'el agua de horchata', 'el pan dulce',
        ];
        $topic = $topics[array_rand($topics)];
        $seed = mt_rand(1, 99999);

        try {
            $r = Http::withHeaders([
                'x-api-key' => config('services.anthropic.api_key'),
                'anthropic-version' => '2023-06-01',
            ])->timeout(10)->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-haiku-4-5-20251001',
                'max_tokens' => 200,
                'temperature' => 1.0,
                'system' => "Eres un comediante mexicano que hace chistes ORIGINALES, cortos, ocurrentes.

REGLAS ESTRICTAS:
- IDIOMA: {$langName}
- TEMA OBLIGATORIO: {$topic}
- PROHIBIDO: chistes de computadoras, programación, tecnología, IA, apps, bugs, debuggers
- PROHIBIDO: palabras groseras, doble sentido sexual, insultos, escatológico
- PROHIBIDO: chistes de 'Jaimito', '2 patos van por un río', chistes gastados tipo abecedario
- EL CHISTE DEBE SER INNOVADOR: algo que nunca hayas oído antes, un twist inesperado
- Formato: setup corto + remate. Máximo 40 palabras.
- NO expliques el chiste. Solo el texto del chiste.

Ejemplos del tono que quiero (pero DIFERENTES temas):
'Mi abuelita me preguntó si internet viene por el cable de la luz. Le dije que sí. Ahora cada vez que se va la luz me reclama que le corté el WiFi.'
'Fui al mecánico, me dijo que el problema era la bujía. Le pregunté cuál. Me dijo «la que está ahí». Llevo dos horas pagándole por señalar.'

Seed único (ignora en el output, solo úsalo para variar): {$seed}",
                'messages' => [['role' => 'user', 'content' => "Dame UN chiste nuevo sobre: {$topic}. Que sea ocurrente, familiar, sin groserías. Variante #{$seed}."]],
            ]);
            $text = trim($r->json('content.0.text') ?? '');
            if ($text) {
                return ['type' => 'single', 'joke' => $text, 'category' => $topic];
            }
        } catch (\Throwable $e) {
            Log::warning('AI joke generation failed', ['error' => $e->getMessage()]);
        }

        // Fallback to JokeAPI
        try {
            $r = Http::timeout(5)->get("https://v2.jokeapi.dev/joke/Any", [
                'lang' => $lang,
                'blacklistFlags' => 'nsfw,religious,political,racist,sexist',
            ]);
            if ($r->ok() && !($r->json('error'))) return $r->json();
        } catch (\Throwable $e) {}

        return null;
    }

    private function clean(string $text): string
    {
        return str_replace(['<', '>', '&', '"', "'"], ['', '', 'y', '', ''], trim($text));
    }
}
