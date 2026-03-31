<?php

namespace App\Services;

use App\Exceptions\AudioGenerationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ElevenLabsService
{
    private string $apiKey;
    private string $voiceId;

    public function __construct()
    {
        $this->apiKey = config('services.elevenlabs.api_key');
        $this->voiceId = config('services.elevenlabs.voice_id');
    }

    /**
     * Synthesize text to speech and save as MP3.
     *
     * @return string Relative path to the saved audio file
     */
    public function synthesize(string $text, float $stability = 0.5, float $similarity = 0.75): string
    {
        try {
            $response = Http::withHeaders([
                'xi-api-key' => $this->apiKey,
                'Accept' => 'audio/mpeg',
            ])->timeout(30)->post(
                "https://api.elevenlabs.io/v1/text-to-speech/{$this->voiceId}",
                [
                    'text' => $text,
                    'model_id' => 'eleven_multilingual_v2',
                    'voice_settings' => [
                        'stability' => $stability,
                        'similarity_boost' => $similarity,
                        'style' => 0.3,
                        'use_speaker_boost' => true,
                    ],
                ]
            );

            if ($response->failed()) {
                throw new AudioGenerationException('ElevenLabs API error: ' . $response->status());
            }

            $filename = 'audio/' . Str::ulid() . '.mp3';
            Storage::disk('local')->put($filename, $response->body());

            return $filename;
        } catch (AudioGenerationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Audio generation failed', ['error' => $e->getMessage()]);
            throw new AudioGenerationException('Failed to generate audio: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Synthesize text and convert to mulaw 8kHz for Twilio Media Streams.
     *
     * @return string Base64-encoded mulaw audio ready to stream to Twilio
     */
    public function synthesizeForTwilio(string $text): string
    {
        $mp3Path = $this->synthesize($text);
        $mp3FullPath = Storage::disk('local')->path($mp3Path);

        try {
            $mulawPath = Storage::disk('local')->path('audio/' . Str::ulid() . '.raw');

            $cmd = sprintf(
                'ffmpeg -i %s -ar 8000 -ac 1 -f mulaw %s -y 2>/dev/null',
                escapeshellarg($mp3FullPath),
                escapeshellarg($mulawPath)
            );

            exec($cmd, $output, $exitCode);

            if ($exitCode !== 0 || ! file_exists($mulawPath)) {
                throw new AudioGenerationException('ffmpeg mulaw conversion failed');
            }

            $mulawData = file_get_contents($mulawPath);
            $base64 = base64_encode($mulawData);

            // Cleanup temp files
            @unlink($mulawPath);
            $this->cleanup($mp3Path);

            return $base64;
        } catch (AudioGenerationException $e) {
            $this->cleanup($mp3Path);
            throw $e;
        } catch (\Throwable $e) {
            $this->cleanup($mp3Path);
            throw new AudioGenerationException('Mulaw conversion failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Split base64 mulaw audio into chunks suitable for Twilio Media Streams.
     * Each chunk is ~20ms of audio (160 bytes at 8kHz mulaw).
     *
     * @return array<string> Array of base64-encoded chunks
     */
    public static function chunkMulawAudio(string $base64Audio, int $chunkSize = 160): array
    {
        $raw = base64_decode($base64Audio);
        $chunks = str_split($raw, $chunkSize);

        return array_map(fn($chunk) => base64_encode($chunk), $chunks);
    }

    /**
     * Delete a previously generated audio file.
     */
    public function cleanup(string $path): void
    {
        Storage::disk('local')->delete($path);
    }
}
