<?php

namespace App\WebSockets;

use App\Services\ClaudeJokeService;
use App\Services\ElevenLabsService;
use Illuminate\Support\Facades\Log;
use Ratchet\RFC6455\Messaging\Frame;
use React\Stream\ThroughStream;

class MediaStreamServer
{
    private array $sessions = [];

    public function onOpen(string $connId, ThroughStream $stream, string $path): void
    {
        // Parse scenario---character from path
        $parts = explode('/', trim($path, '/'));
        $encoded = end($parts);
        $split = explode('---', urldecode($encoded), 2);

        $this->sessions[$connId] = [
            'stream' => $stream,
            'streamSid' => null,
            'scenario' => $split[0] ?? '',
            'character' => $split[1] ?? '',
            'conversation' => [],
            'turnCount' => 0,
            'isPlaying' => false,
        ];

        Log::info('WS opened', ['connId' => $connId, 'scenario' => $split[0] ?? '']);
    }

    public function onTextMessage(string $connId, string $raw): void
    {
        $session = &$this->sessions[$connId] ?? null;
        if (!$session) return;

        $data = json_decode($raw, true);
        if (!$data) return;

        $event = $data['event'] ?? '';

        switch ($event) {
            case 'connected':
                Log::info('Twilio connected', ['connId' => $connId]);
                break;

            case 'start':
                $session['streamSid'] = $data['start']['streamSid'] ?? null;
                Log::info('Stream started', ['connId' => $connId, 'sid' => $session['streamSid']]);
                // Don't speak first — wait for caller's greeting
                break;

            case 'media':
                if ($session['isPlaying']) break;
                // For now we don't have Deepgram STT, so we use a timer-based approach
                // After the stream starts, wait a few seconds then respond
                if (!isset($session['firstMediaReceived'])) {
                    $session['firstMediaReceived'] = true;
                    // Use a simple approach: respond after receiving first audio
                    $this->respondToSpeech($connId, 'Bueno?');
                }
                break;

            case 'mark':
                $session['isPlaying'] = false;
                // After AI finishes speaking, listen for more
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

        // Get AI reply
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
        $stream = $session['stream'];

        try {
            $tts = app(ElevenLabsService::class);
            $base64Audio = $tts->synthesizeForTwilio($text);
            $chunks = ElevenLabsService::chunkMulawAudio($base64Audio);

            foreach ($chunks as $chunk) {
                $this->sendWsJson($stream, [
                    'event' => 'media',
                    'streamSid' => $streamSid,
                    'media' => ['payload' => $chunk],
                ]);
            }

            $this->sendWsJson($stream, [
                'event' => 'mark',
                'streamSid' => $streamSid,
                'mark' => ['name' => 'speech_done'],
            ]);

            Log::info('Spoke', ['connId' => $connId, 'text' => substr($text, 0, 50)]);
        } catch (\Throwable $e) {
            Log::error('TTS failed', ['error' => $e->getMessage()]);
            $session['isPlaying'] = false;
        }
    }

    private function sendWsJson(ThroughStream $stream, array $data): void
    {
        $json = json_encode($data);
        $frame = new Frame($json, true, Frame::OP_TEXT);
        $stream->write($frame->getContents());
    }

    public function onConnectionClose(string $connId): void
    {
        Log::info('WS closed', ['connId' => $connId]);
        unset($this->sessions[$connId]);
    }
}
