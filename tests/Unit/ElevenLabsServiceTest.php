<?php

namespace Tests\Unit;

use App\Exceptions\AudioGenerationException;
use App\Services\ElevenLabsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ElevenLabsServiceTest extends TestCase
{
    public function test_synthesize_saves_audio_file(): void
    {
        Storage::fake('local');

        Http::fake([
            'api.elevenlabs.io/*' => Http::response('fake-audio-bytes', 200),
        ]);

        $service = new ElevenLabsService();
        $path = $service->synthesize('Hola mundo');

        $this->assertStringStartsWith('audio/', $path);
        $this->assertStringEndsWith('.mp3', $path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_synthesize_throws_on_api_error(): void
    {
        Http::fake([
            'api.elevenlabs.io/*' => Http::response([], 500),
        ]);

        $this->expectException(AudioGenerationException::class);

        $service = new ElevenLabsService();
        $service->synthesize('test');
    }

    public function test_cleanup_deletes_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('audio/test.mp3', 'content');

        $service = new ElevenLabsService();
        $service->cleanup('audio/test.mp3');

        Storage::disk('local')->assertMissing('audio/test.mp3');
    }

    public function test_chunk_mulaw_audio_splits_correctly(): void
    {
        // 320 bytes of raw audio = 2 chunks of 160
        $raw = str_repeat('x', 320);
        $base64 = base64_encode($raw);

        $chunks = ElevenLabsService::chunkMulawAudio($base64, 160);

        $this->assertCount(2, $chunks);
        $this->assertEquals(160, strlen(base64_decode($chunks[0])));
        $this->assertEquals(160, strlen(base64_decode($chunks[1])));
    }
}
