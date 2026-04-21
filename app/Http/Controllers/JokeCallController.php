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

    private function fetchJoke(string $lang): ?array
    {
        $langNames = ['es' => 'Spanish', 'en' => 'English', 'de' => 'German', 'pt' => 'Portuguese', 'fr' => 'French'];
        $langName = $langNames[$lang] ?? 'Spanish';

        $topics = [
            // Familia
            'abuelitas mexicanas', 'abuelitos', 'suegras', 'tíos chismosos', 'primos lejanos',
            'hermanos que no se bañan', 'el bebé de la familia', 'papás regañones', 'mamás preocuponas',
            'la quinceañera', 'la boda', 'la primera comunión', 'el bautizo', 'el cumpleaños',
            'la posada', 'el pariente borracho', 'la reunión familiar', 'herencias peleadas',

            // Trabajo y oficinas
            'el jefe insoportable', 'el compañero que huele feo', 'la junta que pudo ser un correo',
            'el empleado que llega tarde', 'viernes de quincena', 'el contador', 'el vendedor de seguros',
            'recursos humanos', 'el nuevo becario', 'el office manager', 'fin de semana laboral',

            // Servicios
            'el doctor', 'el dentista', 'el psicólogo', 'el veterinario', 'el abogado', 'el contador',
            'el mecánico', 'el plomero', 'el electricista', 'el albañil', 'el carpintero',
            'el barbero', 'la manicurista', 'la estilista', 'el masajista',

            // Comida y bebida
            'los tacos al pastor', 'las tortas', 'los elotes', 'el pozole', 'las enchiladas',
            'la birria', 'el mole', 'las chilaquiles', 'el aguachile', 'los tamales',
            'el agua de horchata', 'el atole', 'el tejuino', 'el mezcal', 'la cerveza fría',
            'el pan dulce', 'los churros', 'las paletas de hielo', 'el raspado',

            // Transporte
            'el tráfico de CDMX', 'el tráfico de Monterrey', 'el metro lleno', 'el pesero',
            'el uber', 'el didi', 'el taxista chismoso', 'el viaje compartido incómodo',
            'los baches del DF', 'el segundo piso del periférico', 'las vías rápidas',

            // Ciudad y barrios
            'la tiendita de la esquina', 'el tianguis', 'el mercado', 'el súper mercado',
            'el abarrotes', 'el OXXO a las 3am', 'el lavado de autos', 'la peluquería de barrio',
            'el mercado sobre ruedas', 'la plaza del centro',

            // Amigos y pareja
            'el novio celoso', 'la novia celosa', 'el ex mensajero', 'la mejor amiga entrometida',
            'el amigo que nunca paga', 'el roomate caótico', 'las despedidas de soltero',
            'los compas de la prepa', 'las tardes de chelas',

            // Niños y escuela
            'los niños en la escuela', 'el maestro estricto', 'la tarea imposible', 'el recreo',
            'las cooperativas escolares', 'el padre de familia pesado', 'las juntas de padres',

            // Animales
            'los perritos', 'los gatos traviesos', 'los pájaros del parque', 'el loro hablador',
            'el pez que se murió', 'el perrito callejero', 'el gato del vecino',

            // Deportes y cultura
            'el partido del Tri', 'el América vs Chivas', 'los mariachis', 'la banda del pueblo',
            'el concierto de fresa', 'la ópera en Bellas Artes', 'la lucha libre',

            // Tecnología (sin programación)
            'el WhatsApp de la familia', 'los memes de internet', 'TikTok para papás',
            'el celular sin batería', 'el wifi lento', 'Netflix compartido', 'el video de YouTube de 10 horas',

            // Eventos
            'las vacaciones', 'la aerolínea que pierde maletas', 'el hotel todo incluido',
            'el crucero', 'el viaje de fin de año', 'el concierto cancelado', 'la fiesta sin invitados',

            // Absurdo cotidiano
            'el calor extremo', 'la lluvia inoportuna', 'el corte de luz', 'el silbato del camotero',
            'el afilador de cuchillos', 'el tamalero en la madrugada', 'el camión de la basura',
            'las alarmas sísmicas', 'el fin del mundo',

            // Chistes de situación mexicana
            'ahorita lo arreglo', 'se me hizo tarde', 'es que no me dijeron', 'siempre sí voy',
            'luego te pago', 'mañana sin falta', 'está bien ahí',
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
