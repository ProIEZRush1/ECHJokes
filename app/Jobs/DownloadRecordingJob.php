<?php

namespace App\Jobs;

use App\Models\JokeCall;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DownloadRecordingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 3;

    public function __construct(
        public JokeCall $jokeCall,
        public string $recordingUrl,
    ) {
        $this->onQueue('recordings');
    }

    public function handle(): void
    {
        try {
            // Twilio serves recordings as WAV by default, append .mp3 for MP3
            $url = $this->recordingUrl . '.mp3';

            $response = Http::withBasicAuth(
                config('services.twilio.sid'),
                config('services.twilio.auth_token')
            )->timeout(30)->get($url);

            if ($response->failed()) {
                throw new \RuntimeException('Failed to download recording: ' . $response->status());
            }

            $path = "recordings/{$this->jokeCall->session_id}.mp3";
            Storage::disk('public')->put($path, $response->body());

            $this->jokeCall->update([
                'recording_url' => Storage::disk('public')->url($path),
            ]);
        } catch (\Throwable $e) {
            Log::error('Recording download failed', [
                'joke_call_id' => $this->jokeCall->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
