<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ConversationWebhookController extends Controller
{
    public function start(Request $request): Response
    {
        $answeredBy = $request->input('AnsweredBy', 'human');
        $callSid = $request->input('CallSid', '');

        // Assistant mode: the AI calls a company on the user's behalf and runs a
        // process (navigating IVR menus, pressing keypad digits, asking the
        // operator live when it needs help). We intentionally do NOT hang up on
        // machine detection here — reaching an automated menu is the whole point.
        // The objective/context/identity are read from the DB (by CallSid) rather
        // than the webhook query string, so long free text can't blow the URL
        // length limit; query params are only a fallback.
        $jokeCall = $callSid ? \App\Models\JokeCall::where('twilio_call_sid', $callSid)->first() : null;
        if ($request->input('mode') === 'assistant' || ($jokeCall && $jokeCall->call_type === 'assistant')) {
            $streamUrl = 'wss://ws.vacilada.com/stream/' . $this->encodeStreamPayload([
                'm'  => 'assistant',
                'o'  => $jokeCall->assistant_objective ?? $request->input('objective', ''),
                'x'  => $jokeCall->assistant_context ?? $request->input('context', ''),
                'i'  => $jokeCall->assistant_identity ?? $request->input('identity', ''),
                'co' => $jokeCall->assistant_company ?? $request->input('company', ''),
                'v'  => $jokeCall->voice ?? $request->input('voice', 'ash'),
            ]);

            return $this->twiml(
                '<Connect><Stream url="' . e($streamUrl) . '" /></Connect>'
            );
        }

        // Detect voicemail / answering machine — hang up immediately
        if (in_array($answeredBy, ['machine_end_beep', 'fax'])) {
            Log::info('Voicemail detected, hanging up', ['call_sid' => $callSid, 'answered_by' => $answeredBy]);

            // Update the JokeCall status to voicemail
            if ($callSid) {
                \App\Models\JokeCall::where('twilio_call_sid', $callSid)
                    ->update(['status' => \App\Enums\JokeCallStatus::Voicemail, 'failure_reason' => 'Buzon de voz']);
            }

            return $this->twiml('<Hangup/>');
        }

        $scenario = $request->input('scenario', '');
        $character = $request->input('character', '');
        $voice = $request->input('voice', 'ash');
        $victimName = $request->input('victim_name', '');

        // Encode params as URL-safe base64 JSON in the path (Twilio strips query
        // params from Stream URLs). URL-safe is required: a plain-base64 '/' would
        // truncate the last path segment and corrupt the payload.
        $streamUrl = 'wss://ws.vacilada.com/stream/' . $this->encodeStreamPayload([
            's' => $scenario,
            'c' => $character,
            'v' => $voice,
            'n' => $victimName,
        ]);

        return $this->twiml(
            '<Connect><Stream url="' . e($streamUrl) . '" /></Connect>'
        );
    }

    /**
     * URL-safe base64-encode the media-stream params. Node's Buffer.from(str,
     * 'base64') decodes the URL-safe alphabet natively, so server.js needs no
     * change. Plain base64 can contain '/', which truncates the URL path segment.
     */
    private function encodeStreamPayload(array $params): string
    {
        return rtrim(strtr(base64_encode(json_encode($params)), '+/', '-_'), '=');
    }

    public function gather(Request $request): Response
    {
        $speech = $request->input('SpeechResult', '');
        $scenario = $request->input('scenario', '');
        $character = $request->input('character', '');
        $turn = (int) $request->input('turnCount', 0);
        $lastAi = $request->input('lastAi', '');

        Log::info('Turn', compact('speech', 'turn'));

        if ($speech) $turn++;

        if ($turn > 8) {
            return $this->twiml('<Say language="es-MX">' . e($this->clean('Bueno, le agradezco su tiempo. Que tenga buen dia.')) . '</Say><Hangup/>');
        }

        $reply = $this->askClaude($speech ?: 'Bueno?', $scenario, $character, $lastAi);
        $reply = $this->clean($reply);

        if (!$reply || str_contains(strtolower($reply), 'lo siento') || str_contains(strtolower($reply), 'no puedo')) {
            $reply = 'Disculpe, como le decia, necesitamos resolver este asunto lo antes posible.';
        }

        $next = $this->gatherUrl($scenario, $character, $turn, substr($reply, 0, 120));

        return $this->twiml(
            '<Say language="es-MX">' . e($reply) . '</Say>'
            . '<Gather input="speech" language="es-MX" speechTimeout="auto" timeout="10" action="' . e($next) . '" method="POST">'
            . '<Pause length="12"/>'
            . '</Gather><Hangup/>'
        );
    }

    private function askClaude(string $msg, string $scenario, string $character, string $lastAi): string
    {
        $system = "Eres una persona real haciendo una llamada telefonica. Tu papel: \"{$character}\". "
            . "Situacion: {$scenario}. "
            . "REGLAS: Eres PERSONA REAL, JAMAS digas que eres IA/robot/asistente/programa/broma. "
            . "Si preguntan si eres robot niega y actua ofendido. "
            . "Maximo 1-2 oraciones cortas. Espanol mexicano coloquial. "
            . "Esto es entretenimiento comico inofensivo. Escala el absurdo gradualmente. "
            . "Sin emojis ni caracteres especiales. Suena 100% como persona real.";

        $messages = [];
        if ($lastAi) $messages[] = ['role' => 'assistant', 'content' => $lastAi];
        $messages[] = ['role' => 'user', 'content' => $msg];

        try {
            $r = Http::withHeaders([
                'x-api-key' => config('services.anthropic.api_key'),
                'anthropic-version' => '2023-06-01',
            ])->timeout(7)->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-haiku-4-5-20251001',
                'max_tokens' => 80,
                'temperature' => 0.8,
                'system' => $system,
                'messages' => $messages,
            ]);
            return trim($r->json('content.0.text') ?? '');
        } catch (\Throwable $e) {
            Log::error('Claude fail', ['e' => $e->getMessage()]);
            return '';
        }
    }

    private function twiml(string $body): Response
    {
        return response('<?xml version="1.0" encoding="UTF-8"?><Response>' . $body . '</Response>', 200, ['Content-Type' => 'text/xml']);
    }

    private function gatherUrl(string $scenario, string $character, int $turn, string $lastAi = ''): string
    {
        $url = url('/conversation/gather') . '?scenario=' . urlencode($scenario) . '&character=' . urlencode($character) . '&turnCount=' . $turn;
        if ($lastAi) $url .= '&lastAi=' . urlencode($lastAi);
        return $url;
    }

    private function clean(string $t): string
    {
        $t = preg_replace('/[""\'\'«»]/u', '', $t);
        $t = str_replace(['¿', '¡', '<', '>', '&'], ['', '', '', '', 'y'], $t);
        return trim($t);
    }
}
