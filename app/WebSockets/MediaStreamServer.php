<?php

namespace App\WebSockets;

use App\Services\ClaudeJokeService;
use App\Services\ElevenLabsService;
use Illuminate\Support\Facades\Log;
use React\Socket\ConnectionInterface;

class MediaStreamServer
{
    private array $sessions = [];

    public function onOpen(string $connId, ConnectionInterface $conn, string $path): void
    {
        $parts = explode('/', trim($path, '/'));
        $encoded = end($parts);
        $split = explode('---', urldecode($encoded), 2);

        $this->sessions[$connId] = [
            'conn' => $conn,
            'streamSid' => null,
            'scenario' => $split[0] ?? '',
            'character' => $split[1] ?? '',
            'conversation' => [],
            'turnCount' => 0,
            'isPlaying' => false,
            'firstMediaReceived' => false,
        ];

        Log::info('WS open', ['connId' => $connId, 'scenario' => $split[0] ?? '']);
    }

    public function onTextMessage(string $connId, string $raw): void
    {
        $session = &$this->sessions[$connId];
        if (!$session) return;

        $data = json_decode($raw, true);
        if (!$data) return;

        $event = $data['event'] ?? '';
        Log::info('WS event', ['connId' => $connId, 'event' => $event]);

        switch ($event) {
            case 'connected':
                Log::info('Twilio connected', ['connId' => $connId]);
                break;

            case 'start':
                $session['streamSid'] = $data['start']['streamSid'] ?? null;
                Log::info('Stream started', ['connId' => $connId, 'sid' => $session['streamSid']]);
                break;

            case 'media':
                if ($session['isPlaying']) break;
                // On first audio received, wait a moment then respond
                if (!$session['firstMediaReceived']) {
                    $session['firstMediaReceived'] = true;
                    // Respond to the caller's greeting
                    $this->respondToSpeech($connId, 'Bueno');
                }
                break;

            case 'mark':
                $session['isPlaying'] = false;
                break;

            case 'stop':
                $this->onConnectionClose($connId);
                break;
        }
    }

    private function respondToSpeech(string $connId, string $speechText): void
    {
        $session = &$this->sessions[$connId];
        if (!$session || $session['isPlaying']) return;

        $session['conversation'][] = ['role' => 'human', 'text' => $speechText];
        $session['turnCount']++;

        Log::info('Responding', ['connId' => $connId, 'turn' => $session['turnCount']]);

        if ($session['turnCount'] > 8) {
            $this->speak($connId, 'Bueno, le agradezco su tiempo. Que tenga buen dia.');
            return;
        }

        $claude = app(ClaudeJokeService::class);
        $reply = $claude->generateConversationReply(
            $session['conversation'],
            $session['scenario'],
            ['character' => $session['character'], 'context' => $session['scenario'], 'escalation' => []]
        );

        if (empty($reply) || str_contains(strtolower($reply), 'lo siento') || str_contains(strtolower($reply), 'no puedo')) {
            $reply = 'Disculpe, como le decia, necesitamos resolver este asunto.';
        }

        $session['conversation'][] = ['role' => 'ai', 'text' => $reply];
        $this->speak($connId, $reply);
    }

    private function speak(string $connId, string $text): void
    {
        $session = &$this->sessions[$connId];
        if (!$session) return;

        $session['isPlaying'] = true;
        $streamSid = $session['streamSid'];
        $conn = $session['conn'];

        try {
            $tts = app(ElevenLabsService::class);
            $base64Audio = $tts->synthesizeForTwilio($text);
            $chunks = ElevenLabsService::chunkMulawAudio($base64Audio);

            Log::info('Speaking', ['connId' => $connId, 'chunks' => count($chunks), 'text' => substr($text, 0, 50)]);

            foreach ($chunks as $chunk) {
                $this->sendWsText($conn, json_encode([
                    'event' => 'media',
                    'streamSid' => $streamSid,
                    'media' => ['payload' => $chunk],
                ]));
            }

            $this->sendWsText($conn, json_encode([
                'event' => 'mark',
                'streamSid' => $streamSid,
                'mark' => ['name' => 'speech_done'],
            ]));
        } catch (\Throwable $e) {
            Log::error('TTS failed', ['error' => $e->getMessage()]);
            $session['isPlaying'] = false;
        }
    }

    private function sendWsText(ConnectionInterface $conn, string $text): void
    {
        $len = strlen($text);
        if ($len < 126) {
            $header = chr(0x81) . chr($len);
        } elseif ($len < 65536) {
            $header = chr(0x81) . chr(126) . pack('n', $len);
        } else {
            $header = chr(0x81) . chr(127) . pack('J', $len);
        }
        $conn->write($header . $text);
    }

    public function onConnectionClose(string $connId): void
    {
        Log::info('WS closed', ['connId' => $connId]);
        unset($this->sessions[$connId]);
    }
}
