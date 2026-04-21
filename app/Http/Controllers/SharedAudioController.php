<?php

namespace App\Http\Controllers;

use App\Models\JokeCall;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class SharedAudioController extends Controller
{
    public function stream(Request $request, string $sessionId): Response
    {
        $call = JokeCall::where('session_id', $sessionId)->firstOrFail();
        $cachePath = "shared/{$sessionId}.mp3";

        if (Storage::disk('local')->exists($cachePath)) {
            return response()->file(Storage::disk('local')->path($cachePath), ['Content-Type' => 'audio/mpeg']);
        }

        if (!$call->recording_url) abort(404, 'No recording available');

        try {
            $resp = Http::withBasicAuth(
                config('services.twilio.sid'),
                config('services.twilio.auth_token')
            )->timeout(20)->get($call->recording_url);

            if (!$resp->ok()) {
                Log::warning('Twilio recording download failed', ['call' => $call->id, 'status' => $resp->status()]);
                return redirect($call->recording_url);
            }

            $rawPath = Storage::disk('local')->path($cachePath);
            $dir = dirname($rawPath);
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            $tmp = tempnam(sys_get_temp_dir(), 'call_') . '.mp3';
            file_put_contents($tmp, $resp->body());

            $watermark = public_path('watermark.mp3');
            if (!file_exists($watermark)) $watermark = storage_path('app/public/watermark.mp3');

            if (file_exists($watermark) && $this->hasFfmpeg()) {
                $listFile = tempnam(sys_get_temp_dir(), 'list_') . '.txt';
                file_put_contents($listFile, "file '{$tmp}'\nfile '{$watermark}'\n");
                $cmd = sprintf('ffmpeg -y -f concat -safe 0 -i %s -c copy %s 2>&1', escapeshellarg($listFile), escapeshellarg($rawPath));
                exec($cmd, $out, $code);
                @unlink($listFile);
                @unlink($tmp);
                if ($code !== 0) {
                    Log::warning('ffmpeg concat failed, serving raw', ['code' => $code, 'out' => implode("\n", $out)]);
                    file_put_contents($rawPath, $resp->body());
                }
            } else {
                rename($tmp, $rawPath);
            }

            return response()->file($rawPath, ['Content-Type' => 'audio/mpeg']);
        } catch (\Throwable $e) {
            Log::error('SharedAudio failed', ['err' => $e->getMessage()]);
            return redirect($call->recording_url);
        }
    }

    private function hasFfmpeg(): bool
    {
        @exec('ffmpeg -version 2>&1', $out, $code);
        return $code === 0;
    }
}
