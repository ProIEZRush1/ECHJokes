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
     * Initial call webhook — say the opening line and start listening.
     */
    public function start(JokeCall $jokeCall): Response
    {
        $transcript = $jokeCall->ai_transcript ?? [];
        $opening = $jokeCall->joke_text ?? 'Buenas tardes, disculpe la molestia.';

        // Store the opening in conversation
        $transcript['conversation'] = $transcript['conversation'] ?? [];
        $transcript['conversation'][] = [
            'role' => 'ai',
            'text' => $opening,
            'timestamp' => now()->toIso8601String(),
        ];
        $jokeCall->update(['ai_transcript' => $transcript]);

        $gatherUrl = route('conversation.gather', ['jokeCall' => $jokeCall->id]);

        $twiml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <Response>
            <Say language="es-MX" voice="Polly.Mia">{$this->escape($opening)}</Say>
            <Gather input="speech" language="es-MX" speechTimeout="3" timeout="12" action="{$gatherUrl}" method="POST">
                <Say language="es-MX" voice="Polly.Mia">...</Say>
            </Gather>
            <Say language="es-MX" voice="Polly.Mia">Bueno, parece que no hay nadie. Hasta luego!</Say>
        </Response>
        XML;

        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    /**
     * Gather callback — Claude generates a reply, then listens again.
     */
    public function gather(Request $request, JokeCall $jokeCall): Response
    {
        $speechResult = $request->input('SpeechResult', '');
        $confidence = $request->input('Confidence', '0');

        Log::info('Speech received', [
            'joke_call_id' => $jokeCall->id,
            'speech' => $speechResult,
            'confidence' => $confidence,
        ]);

        $transcript = $jokeCall->ai_transcript ?? [];
        $conversation = $transcript['conversation'] ?? [];
        $prankScript = $transcript['prank_script'] ?? [];
        $scenario = $transcript['scenario'] ?? $jokeCall->custom_joke_prompt ?? '';
        $turnCount = count(array_filter($conversation, fn($t) => $t['role'] === 'human'));

        // Add human turn
        $conversation[] = [
            'role' => 'human',
            'text' => $speechResult,
            'timestamp' => now()->toIso8601String(),
        ];

        // Check if we should end the call (max 6 human turns)
        if ($turnCount >= 5 || empty($speechResult)) {
            $goodbye = 'Bueno, fue un placer. Ah, por cierto, esto fue una broma de ECHJokes punto eme equis. Hasta luego!';
            $conversation[] = [
                'role' => 'ai',
                'text' => $goodbye,
                'timestamp' => now()->toIso8601String(),
            ];

            $transcript['conversation'] = $conversation;
            $jokeCall->update([
                'ai_transcript' => $transcript,
                'status' => \App\Enums\JokeCallStatus::Completed,
            ]);

            $twiml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <Response>
                <Say language="es-MX" voice="Polly.Mia">{$this->escape($goodbye)}</Say>
                <Hangup/>
            </Response>
            XML;

            return response($twiml, 200, ['Content-Type' => 'text/xml']);
        }

        // Generate AI reply
        $claude = app(ClaudeJokeService::class);
        $reply = $claude->generateConversationReply($conversation, $scenario, $prankScript);

        $conversation[] = [
            'role' => 'ai',
            'text' => $reply,
            'timestamp' => now()->toIso8601String(),
        ];

        $transcript['conversation'] = $conversation;
        $jokeCall->update(['ai_transcript' => $transcript]);

        $gatherUrl = route('conversation.gather', ['jokeCall' => $jokeCall->id]);

        $twiml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <Response>
            <Say language="es-MX" voice="Polly.Mia">{$this->escape($reply)}</Say>
            <Gather input="speech" language="es-MX" speechTimeout="3" timeout="12" action="{$gatherUrl}" method="POST">
                <Say language="es-MX" voice="Polly.Mia">...</Say>
            </Gather>
            <Say language="es-MX" voice="Polly.Mia">Bueno, parece que se corto. Hasta luego, esto fue una broma de ECHJokes!</Say>
        </Response>
        XML;

        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
