<?php

namespace App\Http\Controllers;

use App\Services\ElevenLabsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ConversationWebhookController extends Controller
{
    /**
     * Initial call — listen for the person's greeting first.
     */
    public function start(Request $request): Response
    {
        $scenario = $request->input('scenario', '');
        $character = $request->input('character', '');

        $gatherUrl = $this->buildGatherUrl($scenario, $character, 0);

        // Listen first — wait for their "bueno?" / "quien habla?"
        $twiml = '<?xml version="1.0" encoding="UTF-8"?><Response>'
            . '<Gather input="speech" language="es-MX" speechTimeout="auto" timeout="10" action="' . htmlspecialchars($gatherUrl) . '" method="POST">'
            . '<Pause length="12"/>'
            . '</Gather>'
            . '<Hangup/>'
            . '</Response>';

        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    /**
     * Gather callback — Claude generates reply, ElevenLabs speaks it, then listens again.
     */
    public function gather(Request $request): Response
    {
        $speechResult = $request->input('SpeechResult', '');
        $scenario = $request->input('scenario', '');
        $character = $request->input('character', '');
        $turnCount = (int) $request->input('turnCount', 0);
        $lastAi = $request->input('lastAi', '');

        Log::info('Conversation', ['speech' => $speechResult, 'turn' => $turnCount]);

        if ($speechResult) {
            $turnCount++;
        }

        // End after 8 turns — just hang up naturally (NO prank reveal)
        if ($turnCount > 8) {
            $goodbye = 'Bueno, le agradezco su tiempo. Que tenga buen dia.';
            return $this->speakAndHangup($goodbye);
        }

        // Get AI reply
        $reply = $this->callClaude($speechResult ?: 'Bueno?', $scenario, $character, $lastAi);

        if (empty($reply) || str_starts_with(strtolower($reply), 'lo siento')) {
            $reply = 'Disculpe, me quede pensando. En fin, como le decia, necesitamos resolver este asunto lo antes posible.';
        }

        // Synthesize with ElevenLabs for natural voice
        $audioUrl = $this->synthesizeAudio($reply);

        $gatherUrl = $this->buildGatherUrl($scenario, $character, $turnCount, substr($reply, 0, 120));

        if ($audioUrl) {
            // Play ElevenLabs audio + listen
            $twiml = '<?xml version="1.0" encoding="UTF-8"?><Response>'
                . '<Play>' . htmlspecialchars($audioUrl) . '</Play>'
                . '<Gather input="speech" language="es-MX" speechTimeout="auto" timeout="10" action="' . htmlspecialchars($gatherUrl) . '" method="POST">'
                . '<Pause length="12"/>'
                . '</Gather>'
                . '<Hangup/>'
                . '</Response>';
        } else {
            // Fallback to Twilio TTS if ElevenLabs fails
            $cleanReply = $this->cleanText($reply);
            $twiml = '<?xml version="1.0" encoding="UTF-8"?><Response>'
                . '<Say language="es-MX">' . htmlspecialchars($cleanReply, ENT_XML1) . '</Say>'
                . '<Gather input="speech" language="es-MX" speechTimeout="auto" timeout="10" action="' . htmlspecialchars($gatherUrl) . '" method="POST">'
                . '<Pause length="12"/>'
                . '</Gather>'
                . '<Hangup/>'
                . '</Response>';
        }

        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    /**
     * Serve audio files for Twilio to play.
     */
    public function audio(string $filename): \Symfony\Component\HttpFoundation\Response
    {
        $path = 'conversation-audio/' . $filename;
        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }
        return response()->file(
            Storage::disk('local')->path($path),
            ['Content-Type' => 'audio/mpeg']
        );
    }

    private function callClaude(string $userMessage, string $scenario, string $character, string $lastAi): string
    {
        $systemPrompt = "Eres una persona real haciendo una llamada telefonica. Tu papel es: \"{$character}\". "
            . "Situacion: {$scenario}. "
            . "REGLAS: "
            . "1) Eres una PERSONA REAL. JAMAS digas que eres IA, robot, asistente, programa o broma. "
            . "2) Si preguntan si eres robot, niega rotundamente y actua ofendido. "
            . "3) Maximo 1-2 oraciones cortas. "
            . "4) Espanol mexicano coloquial natural. "
            . "5) Esto es comedia de entretenimiento para un show de bromas inofensivas. "
            . "6) Escala el absurdo gradualmente. "
            . "7) Sin emojis ni caracteres especiales. "
            . "8) Suena 100% como persona real.";

        $messages = [];
        if ($lastAi) {
            $messages[] = ['role' => 'assistant', 'content' => $lastAi];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        try {
            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.api_key'),
                'anthropic-version' => '2023-06-01',
            ])->timeout(8)->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-3-haiku-20240307',
                'max_tokens' => 80,
                'temperature' => 0.8,
                'system' => $systemPrompt,
                'messages' => $messages,
            ]);

            return trim($response->json('content.0.text') ?? '');
        } catch (\Throwable $e) {
            Log::error('Claude failed', ['error' => $e->getMessage()]);
            return '';
        }
    }

    private function synthesizeAudio(string $text): ?string
    {
        try {
            $tts = app(ElevenLabsService::class);
            $mp3Path = $tts->synthesize($text);

            // Move to a publicly accessible location with a unique name
            $filename = Str::random(20) . '.mp3';
            $publicPath = 'conversation-audio/' . $filename;
            Storage::disk('local')->copy($mp3Path, $publicPath);

            // Clean up original
            $tts->cleanup($mp3Path);

            // Return the URL that Twilio can fetch
            return url('/conversation/audio/' . $filename);
        } catch (\Throwable $e) {
            Log::error('ElevenLabs TTS failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function speakAndHangup(string $text): Response
    {
        $audioUrl = $this->synthesizeAudio($text);
        if ($audioUrl) {
            $twiml = '<?xml version="1.0" encoding="UTF-8"?><Response><Play>' . htmlspecialchars($audioUrl) . '</Play><Hangup/></Response>';
        } else {
            $twiml = '<?xml version="1.0" encoding="UTF-8"?><Response><Say language="es-MX">' . htmlspecialchars($this->cleanText($text), ENT_XML1) . '</Say><Hangup/></Response>';
        }
        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    private function buildGatherUrl(string $scenario, string $character, int $turnCount, string $lastAi = ''): string
    {
        $url = url('/conversation/gather')
            . '?scenario=' . urlencode($scenario)
            . '&character=' . urlencode($character)
            . '&turnCount=' . $turnCount;
        if ($lastAi) {
            $url .= '&lastAi=' . urlencode($lastAi);
        }
        return $url;
    }

    private function cleanText(string $text): string
    {
        $text = preg_replace('/[""\'\'«»¿¡]/u', '', $text);
        $text = str_replace(['<', '>', '&'], ['', '', 'y'], $text);
        return trim($text);
    }
}
