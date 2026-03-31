<?php

namespace App\WebSockets;

use App\Enums\JokeCallStatus;
use App\Events\JokeCallStatusUpdated;
use App\Models\JokeCall;
use App\Services\ClaudeJokeService;
use App\Services\DeepgramService;
use App\Services\ElevenLabsService;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ServerRequestInterface;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Ratchet\RFC6455\Messaging\MessageBuffer;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use React\Http\Message\Response;
use React\Stream\ThroughStream;

class TwilioMediaStreamHandler
{
    private array $sessions = [];
    private int $connCounter = 0;

    private ElevenLabsService $tts;
    private ClaudeJokeService $claude;

    public function __construct()
    {
        $this->tts = app(ElevenLabsService::class);
        $this->claude = app(ClaudeJokeService::class);
    }

    public function handleUpgrade(ServerRequestInterface $request): Response
    {
        $negotiator = new ServerNegotiator();
        $response = $negotiator->handshake($request);

        if ($response->getStatusCode() !== 101) {
            return new Response($response->getStatusCode(), $response->getHeaders(), (string) $response->getBody());
        }

        $connId = ++$this->connCounter;
        $path = $request->getUri()->getPath();
        $segments = explode('/', trim($path, '/'));
        $sessionId = end($segments);

        $stream = new ThroughStream();

        $messageBuffer = new MessageBuffer(
            new CloseFrameChecker(),
            function ($message) use ($connId, $stream) {
                $this->onMessage($connId, $stream, (string) $message);
            },
            function ($frame) use ($connId) {
                if ($frame->getOpcode() === Frame::OP_CLOSE) {
                    $this->onClose($connId);
                }
            }
        );

        $this->initSession($connId, $sessionId, $stream);

        $stream->on('data', fn($data) => $messageBuffer->onData($data));
        $stream->on('close', fn() => $this->onClose($connId));

        return new Response(101, $response->getHeaders(), $stream);
    }

    private function initSession(int $connId, string $sessionId, ThroughStream $stream): void
    {
        $jokeCall = JokeCall::where('session_id', $sessionId)->first();

        if (! $jokeCall) {
            Log::warning('Media stream: JokeCall not found', ['session_id' => $sessionId]);
            $stream->close();
            return;
        }

        $transcript = $jokeCall->ai_transcript ?? [];
        $prankScript = $transcript['prank_script'] ?? [];
        $scenario = $transcript['scenario'] ?? $jokeCall->custom_joke_prompt ?? '';

        $this->sessions[$connId] = [
            'joke_call' => $jokeCall,
            'stream' => $stream,
            'stream_sid' => null,
            'deepgram' => new DeepgramService(),
            'prank_script' => $prankScript,
            'scenario' => $scenario,
            'conversation' => [],
            'turn_count' => 0,
            'max_turns' => 8, // Max back-and-forth exchanges
            'last_audio_time' => time(),
        ];

        $jokeCall->updateStatus(JokeCallStatus::InProgress);
        broadcast(new JokeCallStatusUpdated($jokeCall));

        Log::info('Media stream opened', ['session_id' => $sessionId]);
    }

    private function onMessage(int $connId, ThroughStream $stream, string $msg): void
    {
        $session = $this->sessions[$connId] ?? null;
        if (! $session) return;

        $data = json_decode($msg, true);
        if (! $data) return;

        match ($data['event'] ?? '') {
            'start' => $this->handleStart($connId, $data),
            'media' => $this->handleMedia($connId, $data),
            'mark' => $this->handleMark($connId, $data),
            'stop' => $this->handleStop($connId),
            default => null,
        };
    }

    private function handleStart(int $connId, array $data): void
    {
        $streamSid = $data['start']['streamSid'] ?? $data['streamSid'] ?? null;
        $this->sessions[$connId]['stream_sid'] = $streamSid;

        // Send the opening line (pre-generated from the prank script)
        $opening = $this->sessions[$connId]['prank_script']['opening']
            ?? $this->sessions[$connId]['joke_call']->joke_text
            ?? 'Buenas tardes, disculpe la molestia.';

        $this->sessions[$connId]['conversation'][] = [
            'role' => 'ai',
            'text' => $opening,
            'timestamp' => now()->toIso8601String(),
        ];

        $this->speakAndSend($connId, $opening, 'opening');

        // Start Deepgram STT
        $deepgram = $this->sessions[$connId]['deepgram'];
        $deepgram->openSession(fn(string $text, float $confidence) => null);
    }

    private function handleMedia(int $connId, array $data): void
    {
        $this->sessions[$connId]['last_audio_time'] = time();

        $payload = $data['media']['payload'] ?? null;
        if (! $payload) return;

        $deepgram = $this->sessions[$connId]['deepgram'] ?? null;
        if ($deepgram && $deepgram->isConnected()) {
            $deepgram->sendAudio($payload);

            $result = $deepgram->receive();
            if ($result && ! empty($result['text'])) {
                $this->handleTranscript($connId, $result['text']);
            }
        }
    }

    private function handleMark(int $connId, array $data): void
    {
        $markName = $data['mark']['name'] ?? '';

        match ($markName) {
            'opening' => $this->afterSpeak($connId),
            'reply' => $this->afterSpeak($connId),
            'goodbye' => $this->finishCall($connId),
            default => null,
        };
    }

    private function afterSpeak(int $connId): void
    {
        // Now listening for the person's response
        $this->sessions[$connId]['last_audio_time'] = time();
    }

    private function handleTranscript(int $connId, string $text): void
    {
        $session = $this->sessions[$connId] ?? null;
        if (! $session || empty($text)) return;

        $this->sessions[$connId]['conversation'][] = [
            'role' => 'human',
            'text' => $text,
            'timestamp' => now()->toIso8601String(),
        ];

        $this->sessions[$connId]['turn_count']++;

        // Check if we should end the call
        if ($this->sessions[$connId]['turn_count'] >= $this->sessions[$connId]['max_turns']) {
            $goodbye = 'Bueno, ya me tengo que ir. Fue un placer hablar con usted. Que tenga buena tarde! Ah, y por cierto... esto fue una broma de ECHJokes punto eme equis. Hasta luego!';
            $this->sessions[$connId]['conversation'][] = [
                'role' => 'ai',
                'text' => $goodbye,
                'timestamp' => now()->toIso8601String(),
            ];
            $this->speakAndSend($connId, $goodbye, 'goodbye');
            return;
        }

        // Generate AI reply in character
        $reply = $this->claude->generateConversationReply(
            $this->sessions[$connId]['conversation'],
            $this->sessions[$connId]['scenario'],
            $this->sessions[$connId]['prank_script']
        );

        $this->sessions[$connId]['conversation'][] = [
            'role' => 'ai',
            'text' => $reply,
            'timestamp' => now()->toIso8601String(),
        ];

        $this->speakAndSend($connId, $reply, 'reply');
    }

    private function speakAndSend(int $connId, string $text, string $markName): void
    {
        $streamSid = $this->sessions[$connId]['stream_sid'] ?? null;
        $stream = $this->sessions[$connId]['stream'] ?? null;

        if (! $streamSid || ! $stream) return;

        try {
            $base64Audio = $this->tts->synthesizeForTwilio($text);
            $chunks = ElevenLabsService::chunkMulawAudio($base64Audio);

            foreach ($chunks as $chunk) {
                $this->sendJson($stream, [
                    'event' => 'media',
                    'streamSid' => $streamSid,
                    'media' => ['payload' => $chunk],
                ]);
            }

            $this->sendJson($stream, [
                'event' => 'mark',
                'streamSid' => $streamSid,
                'mark' => ['name' => $markName],
            ]);
        } catch (\Throwable $e) {
            Log::error('TTS stream failed', ['error' => $e->getMessage(), 'mark' => $markName]);
            $this->handleMark($connId, ['mark' => ['name' => $markName]]);
        }
    }

    private function sendJson(ThroughStream $stream, array $data): void
    {
        $frame = new Frame(json_encode($data), true, Frame::OP_TEXT);
        $stream->write($frame->getContents());
    }

    private function finishCall(int $connId): void
    {
        $session = $this->sessions[$connId] ?? null;
        if (! $session) return;

        $jokeCall = $session['joke_call'];
        try {
            $twilio = new \Twilio\Rest\Client(
                config('services.twilio.sid'),
                config('services.twilio.auth_token')
            );
            if ($jokeCall->twilio_call_sid) {
                $twilio->calls($jokeCall->twilio_call_sid)->update(['status' => 'completed']);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to hang up call', ['error' => $e->getMessage()]);
        }
    }

    private function handleStop(int $connId): void
    {
        $deepgram = $this->sessions[$connId]['deepgram'] ?? null;
        if ($deepgram) $deepgram->close();
    }

    private function onClose(int $connId): void
    {
        $session = $this->sessions[$connId] ?? null;
        if (! $session) return;

        $jokeCall = $session['joke_call'];
        $existingTranscript = $jokeCall->ai_transcript ?? [];

        $jokeCall->update([
            'ai_transcript' => array_merge($existingTranscript, [
                'conversation' => $session['conversation'],
            ]),
            'status' => JokeCallStatus::Completed,
        ]);
        broadcast(new JokeCallStatusUpdated($jokeCall));

        $deepgram = $session['deepgram'] ?? null;
        if ($deepgram) $deepgram->close();

        Log::info('Media stream closed', ['joke_call_id' => $jokeCall->id]);
        unset($this->sessions[$connId]);
    }

    public function onError(int $connId, \Exception $e): void
    {
        Log::error('Media stream error', ['error' => $e->getMessage()]);
    }
}
