<?php

namespace App\Http\Controllers;

use App\Models\JokeCall;
use App\Services\ClaudeJokeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ConversationWebhookController extends Controller
{
    /**
     * Initial call webhook — listen for the person's greeting first.
     */
    public function start(JokeCall $jokeCall): Response
    {
        $gatherUrl = route('conversation.gather', ['jokeCall' => $jokeCall->id]);

        // Listen first — wait for "Bueno?", "Quien habla?", etc.
        $twiml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Response>'
            . '<Gather input="speech" language="es-MX" speechTimeout="auto" timeout="8" action="' . $this->escape($gatherUrl) . '" method="POST">'
            . '<Pause length="10"/>'
            . '</Gather>'
            . '<Say language="es-MX" voice="Google.es-MX-Standard-A">Bueno? Hola?</Say>'
            . '<Gather input="speech" language="es-MX" speechTimeout="auto" timeout="8" action="' . $this->escape($gatherUrl) . '" method="POST">'
            . '<Pause length="8"/>'
            . '</Gather>'
            . '<Hangup/>'
            . '</Response>';

        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    /**
     * Gather callback — Claude generates a reply, then listens again.
     */
    public function gather(Request $request, JokeCall $jokeCall): Response
    {
        $speechResult = $request->input('SpeechResult', '');

        Log::info('Speech received', [
            'joke_call_id' => $jokeCall->id,
            'speech' => $speechResult,
        ]);

        $transcript = $jokeCall->ai_transcript ?? [];
        $conversation = $transcript['conversation'] ?? [];
        $prankScript = $transcript['prank_script'] ?? [];
        $scenario = $transcript['scenario'] ?? $jokeCall->custom_joke_prompt ?? '';
        $turnCount = count(array_filter($conversation, fn($t) => $t['role'] === 'human'));

        // Add human turn
        if ($speechResult) {
            $conversation[] = [
                'role' => 'human',
                'text' => $speechResult,
                'timestamp' => now()->toIso8601String(),
            ];
            $turnCount++;
        }

        // End after 6 human turns
        if ($turnCount >= 6 || (empty($speechResult) && $turnCount > 0)) {
            $goodbye = 'Bueno, fue un placer. Esto fue una broma de ECHJokes. Hasta luego!';
            $conversation[] = ['role' => 'ai', 'text' => $goodbye, 'timestamp' => now()->toIso8601String()];
            $transcript['conversation'] = $conversation;
            $jokeCall->update(['ai_transcript' => $transcript, 'status' => \App\Enums\JokeCallStatus::Completed]);

            return $this->twimlResponse($goodbye, null);
        }

        // Generate AI reply using fast Haiku model
        $claude = app(ClaudeJokeService::class);
        $reply = $claude->generateConversationReply($conversation, $scenario, $prankScript);

        // Clean any XML-breaking characters
        $reply = $this->cleanForTwiml($reply);

        $conversation[] = ['role' => 'ai', 'text' => $reply, 'timestamp' => now()->toIso8601String()];
        $transcript['conversation'] = $conversation;
        $jokeCall->update(['ai_transcript' => $transcript]);

        $gatherUrl = route('conversation.gather', ['jokeCall' => $jokeCall->id]);

        return $this->twimlResponse($reply, $gatherUrl);
    }

    private function twimlResponse(string $text, ?string $gatherUrl): Response
    {
        $escaped = $this->escape($text);

        $twiml = '<?xml version="1.0" encoding="UTF-8"?><Response>';
        $twiml .= '<Say language="es-MX" voice="Google.es-MX-Standard-A">' . $escaped . '</Say>';

        if ($gatherUrl) {
            $twiml .= '<Gather input="speech" language="es-MX" speechTimeout="auto" timeout="10" action="' . $this->escape($gatherUrl) . '" method="POST">';
            $twiml .= '<Pause length="10"/>';
            $twiml .= '</Gather>';
            $twiml .= '<Say language="es-MX" voice="Google.es-MX-Standard-A">Bueno, esto fue broma de ECHJokes. Adios!</Say>';
        } else {
            $twiml .= '<Hangup/>';
        }

        $twiml .= '</Response>';

        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    private function cleanForTwiml(string $text): string
    {
        // Remove characters that break Twilio's Say tag
        $text = preg_replace('/[""«»\x{201C}\x{201D}\x{2018}\x{2019}]/u', '', $text);
        $text = str_replace(['<', '>', '&'], ['', '', 'y'], $text);
        return trim($text);
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
