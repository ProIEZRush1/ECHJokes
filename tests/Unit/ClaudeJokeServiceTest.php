<?php

namespace Tests\Unit;

use App\Exceptions\JokeGenerationException;
use App\Services\ClaudeJokeService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClaudeJokeServiceTest extends TestCase
{
    public function test_generate_prank_script_returns_array(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => '{"character": "administrador del condominio", "opening": "Buenas tardes, le hablo de la administracion.", "context": "Quejarse del ruido de la lavadora", "escalation": ["Amenazar con multa", "Decir que los vecinos firmaron una peticion"]}'],
                ],
            ], 200),
        ]);

        $service = new ClaudeJokeService();
        $script = $service->generatePrankScript('La lavadora hace mucho ruido');

        $this->assertIsArray($script);
        $this->assertArrayHasKey('character', $script);
        $this->assertArrayHasKey('opening', $script);
        $this->assertNotEmpty($script['character']);
    }

    public function test_generate_prank_script_throws_on_api_error(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([], 500),
        ]);

        $this->expectException(JokeGenerationException::class);

        $service = new ClaudeJokeService();
        $service->generatePrankScript('test scenario');
    }

    public function test_generate_prank_script_handles_non_json_response(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'Just some text that is not JSON'],
                ],
            ], 200),
        ]);

        $service = new ClaudeJokeService();
        $script = $service->generatePrankScript('test');

        // Should return fallback structure
        $this->assertArrayHasKey('character', $script);
        $this->assertArrayHasKey('opening', $script);
    }

    public function test_conversation_reply_returns_text(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'Si señor, asi es, tenemos muchas quejas de los vecinos.'],
                ],
            ], 200),
        ]);

        $service = new ClaudeJokeService();
        $reply = $service->generateConversationReply(
            [['role' => 'human', 'text' => 'De que se trata?']],
            'La lavadora hace mucho ruido',
            ['character' => 'administrador', 'context' => 'queja de ruido', 'escalation' => []]
        );

        $this->assertNotEmpty($reply);
    }

    public function test_conversation_reply_returns_fallback_on_error(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([], 500),
        ]);

        $service = new ClaudeJokeService();
        $reply = $service->generateConversationReply(
            [['role' => 'human', 'text' => 'test']],
            'test scenario'
        );

        $this->assertNotEmpty($reply);
    }
}
