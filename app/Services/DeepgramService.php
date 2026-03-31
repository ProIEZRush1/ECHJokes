<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;

class DeepgramService
{
    private string $apiKey;
    private ?\WebSocket\Client $client = null;
    private bool $connected = false;

    public function __construct()
    {
        $this->apiKey = config('services.deepgram.api_key', env('DEEPGRAM_API_KEY'));
    }

    /**
     * Open a real-time STT session with Deepgram.
     *
     * @param callable $onTranscript Called with (string $text, float $confidence) on each final transcript
     * @param callable|null $onError Called with (string $error) on errors
     */
    public function openSession(callable $onTranscript, ?callable $onError = null): void
    {
        $url = 'wss://api.deepgram.com/v1/listen'
            . '?model=nova-2'
            . '&language=es'
            . '&smart_format=true'
            . '&endpointing=500'
            . '&interim_results=false'
            . '&encoding=mulaw'
            . '&sample_rate=8000'
            . '&channels=1';

        try {
            $this->client = new \WebSocket\Client($url, [
                'headers' => [
                    'Authorization' => 'Token ' . $this->apiKey,
                ],
                'timeout' => 30,
            ]);
            $this->connected = true;

            // Start a listener in a separate process/thread would be ideal,
            // but for the ReactPHP event loop we'll poll for messages
        } catch (\Throwable $e) {
            Log::error('Deepgram connection failed', ['error' => $e->getMessage()]);
            if ($onError) {
                $onError($e->getMessage());
            }
        }
    }

    /**
     * Send mulaw audio data to Deepgram.
     *
     * @param string $mulawBase64 Base64-encoded mulaw audio from Twilio
     */
    public function sendAudio(string $mulawBase64): void
    {
        if (! $this->connected || ! $this->client) {
            return;
        }

        try {
            $rawAudio = base64_decode($mulawBase64);
            $this->client->binary($rawAudio);
        } catch (\Throwable $e) {
            Log::error('Deepgram send failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Receive a message from Deepgram (non-blocking check).
     *
     * @return array|null Parsed transcript result or null if no message
     */
    public function receive(): ?array
    {
        if (! $this->connected || ! $this->client) {
            return null;
        }

        try {
            $this->client->setTimeout(0);
            $message = $this->client->receive();

            if ($message === null) {
                return null;
            }

            $data = json_decode($message, true);

            if (! $data || ($data['type'] ?? '') !== 'Results') {
                return null;
            }

            $channel = $data['channel'] ?? [];
            $alternatives = $channel['alternatives'] ?? [];

            if (empty($alternatives)) {
                return null;
            }

            $best = $alternatives[0];
            $transcript = trim($best['transcript'] ?? '');
            $confidence = $best['confidence'] ?? 0.0;
            $isFinal = $data['is_final'] ?? false;

            if (! $isFinal || empty($transcript)) {
                return null;
            }

            return [
                'text' => $transcript,
                'confidence' => $confidence,
            ];
        } catch (\WebSocket\TimeoutException $e) {
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Close the Deepgram WebSocket session.
     */
    public function close(): void
    {
        if ($this->client) {
            try {
                // Send close frame to signal end of audio
                $this->client->close();
            } catch (\Throwable $e) {
                // Ignore close errors
            }
            $this->client = null;
            $this->connected = false;
        }
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }
}
