<?php

namespace App\WebSockets;

use App\Services\ClaudeJokeService;
use App\Services\DeepgramService;
use App\Services\ElevenLabsService;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Stream\ThroughStream;

class MediaStreamServer
{
    private array $sessions = [];

    public function handleUpgrade(ServerRequestInterface $request): Response
    {
        $path = $request->getUri()->getPath();
        // Extract scenario---character from path /stream/{scenario}---{character}
        $parts = explode('/', trim($path, '/'));
        $encoded = end($parts);
        $decoded = urldecode($encoded);
        $split = explode('---', $decoded, 2);
        $scenario = $split[0] ?? '';
        $character = $split[1] ?? '';

        $connId = uniqid('ws_');

        $stream = new ThroughStream();

        $this->sessions[$connId] = [
            'stream' => $stream,
            'streamSid' => null,
            'scenario' => $scenario,
            'character' => $character,
            'conversation' => [],
            'turnCount' => 0,
            'deepgram' => null,
            'isPlaying' => false,
            'silenceTimer' => null,
        ];

        // Handle incoming WebSocket frames as raw data
        $buffer = '';
        $stream->on('data', function ($data) use ($connId, &$buffer) {
            $buffer .= $data;

            // Try to extract complete WebSocket text frames
            while (strlen($buffer) >= 2) {
                $firstByte = ord($buffer[0]);
                $secondByte = ord($buffer[1]);
                $opcode = $firstByte & 0x0F;
                $masked = ($secondByte & 0x80) !== 0;
                $payloadLen = $secondByte & 0x7F;

                $headerLen = 2;
                if ($payloadLen === 126) {
                    if (strlen($buffer) < 4) break;
                    $payloadLen = unpack('n', substr($buffer, 2, 2))[1];
                    $headerLen = 4;
                } elseif ($payloadLen === 127) {
                    if (strlen($buffer) < 10) break;
                    $payloadLen = unpack('J', substr($buffer, 2, 8))[1];
                    $headerLen = 10;
                }

                if ($masked) $headerLen += 4;
                $totalLen = $headerLen + $payloadLen;
                if (strlen($buffer) < $totalLen) break;

                $payload = substr($buffer, $headerLen, $payloadLen);
                if ($masked) {
                    $mask = substr($buffer, $headerLen - 4, 4);
                    for ($i = 0; $i < $payloadLen; $i++) {
                        $payload[$i] = $payload[$i] ^ $mask[$i % 4];
                    }
                }

                $buffer = substr($buffer, $totalLen);

                if ($opcode === 1) { // Text frame
                    $this->onMessage($connId, $payload);
                } elseif ($opcode === 8) { // Close frame
                    $this->onClose($connId);
                }
            }
        });

        $stream->on('close', function () use ($connId) {
            $this->onClose($connId);
        });

        // WebSocket handshake
        $key = $request->getHeaderLine('Sec-WebSocket-Key');
        $accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-5AB5DC11E65B', true));

        return new Response(101, [
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Accept' => $accept,
        ], $stream);
    }

    private function onMessage(string $connId, string $raw): void
    {
        $session = &$this->sessions[$connId];
        if (!$session) return;

        $data = json_decode($raw, true);
        if (!$data) return;

        $event = $data['event'] ?? '';

        switch ($event) {
            case 'start':
                $session['streamSid'] = $data['start']['streamSid'] ?? null;
                Log::info('Stream started', ['connId' => $connId, 'streamSid' => $session['streamSid']]);

                // Initialize Deepgram STT
                $session['deepgram'] = new DeepgramService();
                $session['deepgram']->openSession(fn() => null);

                // Don't speak first — wait for the person's greeting
                break;

            case 'media':
                $payload = $data['media']['payload'] ?? '';
                if (!$payload || $session['isPlaying']) break;

                // Forward audio to Deepgram
                $deepgram = $session['deepgram'];
                if ($deepgram && $deepgram->isConnected()) {
                    $deepgram->sendAudio($payload);
                    $result = $deepgram->receive();
                    if ($result && !empty($result['text'])) {
                        $this->handleSpeech($connId, $result['text']);
                    }
                }
                break;

            case 'mark':
                $session['isPlaying'] = false;
                break;

            case 'stop':
                $this->onClose($connId);
                break;
        }
    }

    private function handleSpeech(string $connId, string $text): void
    {
        $session = &$this->sessions[$connId];
        if (!$session || $session['isPlaying']) return;

        $session['conversation'][] = ['role' => 'human', 'text' => $text];
        $session['turnCount']++;

        Log::info('Speech detected', ['connId' => $connId, 'text' => $text, 'turn' => $session['turnCount']]);

        if ($session['turnCount'] > 8) {
            $this->speakAndClose($connId, 'Bueno, le agradezco su tiempo. Que tenga buen dia.');
            return;
        }

        // Get AI reply
        $claude = app(ClaudeJokeService::class);
        $lastAi = '';
        foreach (array_reverse($session['conversation']) as $turn) {
            if ($turn['role'] === 'ai') { $lastAi = $turn['text']; break; }
        }

        $reply = $claude->generateConversationReply(
            $session['conversation'],
            $session['scenario'],
            ['character' => $session['character'], 'context' => $session['scenario'], 'escalation' => []]
        );

        if (empty($reply) || str_contains(strtolower($reply), 'lo siento') || str_contains(strtolower($reply), 'no puedo')) {
            $reply = 'Disculpe, como le decia, necesitamos resolver este asunto.';
        }

        $session['conversation'][] = ['role' => 'ai', 'text' => $reply];

        // Synthesize with ElevenLabs and stream back
        $this->speakToStream($connId, $reply);
    }

    private function speakToStream(string $connId, string $text): void
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
                $this->sendWsText($stream, json_encode([
                    'event' => 'media',
                    'streamSid' => $streamSid,
                    'media' => ['payload' => $chunk],
                ]));
            }

            // Mark to know when playback finishes
            $this->sendWsText($stream, json_encode([
                'event' => 'mark',
                'streamSid' => $streamSid,
                'mark' => ['name' => 'speech_done'],
            ]));
        } catch (\Throwable $e) {
            Log::error('TTS failed', ['error' => $e->getMessage()]);
            $session['isPlaying'] = false;
        }
    }

    private function speakAndClose(string $connId, string $text): void
    {
        $this->speakToStream($connId, $text);
        // Connection will close when Twilio ends the call
    }

    private function sendWsText(ThroughStream $stream, string $text): void
    {
        $len = strlen($text);
        if ($len < 126) {
            $header = chr(0x81) . chr($len);
        } elseif ($len < 65536) {
            $header = chr(0x81) . chr(126) . pack('n', $len);
        } else {
            $header = chr(0x81) . chr(127) . pack('J', $len);
        }
        $stream->write($header . $text);
    }

    private function onClose(string $connId): void
    {
        $session = $this->sessions[$connId] ?? null;
        if (!$session) return;

        if ($session['deepgram']) {
            $session['deepgram']->close();
        }

        Log::info('Stream closed', ['connId' => $connId]);
        unset($this->sessions[$connId]);
    }
}
