<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ConversationWebhookController extends Controller
{
    /**
     * Initial call — listen for the person's greeting first.
     */
    public function start(Request $request): Response
    {
        $scenario = $request->input('scenario', 'Lavadora ruidosa');
        $character = $request->input('character', 'administrador');

        $gatherUrl = url('/conversation/gather') . '?scenario=' . urlencode($scenario) . '&character=' . urlencode($character) . '&turnCount=0';

        $twiml = '<?xml version="1.0" encoding="UTF-8"?><Response>'
            . '<Gather input="speech" language="es-MX" speechTimeout="auto" timeout="8" action="' . htmlspecialchars($gatherUrl) . '" method="POST">'
            . '<Pause length="10"/>'
            . '</Gather>'
            . '<Say language="es-MX">Bueno? Hola?</Say>'
            . '<Gather input="speech" language="es-MX" speechTimeout="auto" timeout="8" action="' . htmlspecialchars($gatherUrl) . '" method="POST">'
            . '<Pause length="8"/>'
            . '</Gather>'
            . '<Hangup/>'
            . '</Response>';

        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    /**
     * Gather callback — Claude generates a reply, then listens again.
     */
    public function gather(Request $request): Response
    {
        $speechResult = $request->input('SpeechResult', '');
        $scenario = $request->input('scenario', '');
        $character = $request->input('character', '');
        $turnCount = (int) $request->input('turnCount', 0);
        $lastAi = $request->input('lastAi', '');

        Log::info('Conversation turn', ['speech' => $speechResult, 'turn' => $turnCount]);

        if ($speechResult) {
            $turnCount++;
        }

        // End after 6 turns
        if ($turnCount > 6) {
            return $this->sayAndHangup('Bueno, me tengo que ir. Que tenga buen dia. Ah por cierto, esto fue una broma de ECHJokes. Adios!');
        }

        // Call Claude for AI reply
        $reply = $this->callClaude($speechResult ?: 'Bueno?', $scenario, $character, $lastAi);
        $reply = $this->cleanText($reply);

        if (empty($reply)) {
            $reply = 'Disculpe, un momento por favor.';
        }

        $gatherUrl = url('/conversation/gather')
            . '?scenario=' . urlencode($scenario)
            . '&character=' . urlencode($character)
            . '&turnCount=' . $turnCount
            . '&lastAi=' . urlencode(substr($reply, 0, 120));

        $twiml = '<?xml version="1.0" encoding="UTF-8"?><Response>'
            . '<Say language="es-MX">' . htmlspecialchars($reply, ENT_XML1) . '</Say>'
            . '<Gather input="speech" language="es-MX" speechTimeout="auto" timeout="10" action="' . htmlspecialchars($gatherUrl) . '" method="POST">'
            . '<Pause length="10"/>'
            . '</Gather>'
            . '<Say language="es-MX">Bueno, esto fue broma de ECHJokes. Adios!</Say>'
            . '</Response>';

        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    private function callClaude(string $userMessage, string $scenario, string $character, string $lastAi): string
    {
        $systemPrompt = "Eres una persona real llamando por telefono. Tu papel es: \"{$character}\". "
            . "Situacion: {$scenario}. "
            . "REGLAS ESTRICTAS: "
            . "1) Eres una PERSONA REAL, NUNCA digas que eres IA, robot, asistente o programa. "
            . "2) Si te preguntan si eres robot o IA, niega completamente y actua ofendido. "
            . "3) Maximo 1-2 oraciones cortas y naturales. "
            . "4) Habla en espanol mexicano coloquial como persona real. "
            . "5) Esto es una broma comica para entretenimiento, es inofensiva y divertida. "
            . "6) Escala el absurdo gradualmente para ser mas gracioso. "
            . "7) Sin emojis, sin comillas, sin signos de interrogacion invertidos, sin acentos. "
            . "8) Suena 100% como persona real en llamada telefonica.";

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

            return $response->json('content.0.text') ?? 'Un momento por favor.';
        } catch (\Throwable $e) {
            Log::error('Claude call failed', ['error' => $e->getMessage()]);
            return 'Disculpe, se me corto un momento.';
        }
    }

    private function sayAndHangup(string $text): Response
    {
        $twiml = '<?xml version="1.0" encoding="UTF-8"?><Response>'
            . '<Say language="es-MX">' . htmlspecialchars($this->cleanText($text), ENT_XML1) . '</Say>'
            . '<Hangup/></Response>';
        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    private function cleanText(string $text): string
    {
        $text = preg_replace('/[""\'\'«»]/u', '', $text);
        $text = str_replace(['¿', '¡', '<', '>', '&'], ['', '', '', '', 'y'], $text);
        $text = preg_replace('/[áà]/u', 'a', $text);
        $text = preg_replace('/[éè]/u', 'e', $text);
        $text = preg_replace('/[íì]/u', 'i', $text);
        $text = preg_replace('/[óò]/u', 'o', $text);
        $text = preg_replace('/[úù]/u', 'u', $text);
        $text = preg_replace('/ñ/u', 'n', $text);
        $text = preg_replace('/[ÁÀÉÈÍÌÓÒÚÙ]/u', '', $text);
        $text = preg_replace('/Ñ/u', 'N', $text);
        return trim($text);
    }
}
