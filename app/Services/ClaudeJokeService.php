<?php

namespace App\Services;

use App\Exceptions\JokeGenerationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeJokeService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key');
    }

    /**
     * Generate a prank call script from a scenario description.
     *
     * Returns a JSON object with:
     * - character: who the AI pretends to be
     * - opening: the first line of the call
     * - context: background info for the AI to stay in character
     */
    public function generatePrankScript(string $scenario): array
    {
        $systemPrompt = <<<PROMPT
        Eres un experto en bromas telefonicas. El usuario te da una situacion o escenario y tu creas un guion para una llamada de broma.

        Dado el escenario del usuario, genera un JSON con estos campos:
        - "character": quien va a ser el personaje que llama (ej: "administrador del condominio", "veterinario", "tecnico de internet")
        - "opening": la primera frase que dira el personaje al contestar (ej: "Buenas tardes, le hablo de la administracion del condominio...")
        - "context": instrucciones internas sobre como mantener el personaje, que temas tocar, como escalar la broma de forma graciosa
        - "escalation": 3 puntos de escalacion comica que el personaje puede usar si la conversacion se alarga

        REGLAS:
        - Todo en espanol mexicano coloquial
        - El personaje debe sonar convincente al inicio
        - La broma debe ser inofensiva y graciosa, nunca amenazante
        - El opening debe ser natural, como una llamada real
        - Responde SOLO el JSON, sin texto adicional
        PROMPT;

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-sonnet-4-20250514',
                'max_tokens' => 800,
                'temperature' => 0.9,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => "Escenario de broma: {$scenario}"],
                ],
            ]);

            if ($response->failed()) {
                throw new JokeGenerationException('Anthropic API error: ' . $response->status());
            }

            $content = trim($response->json('content.0.text') ?? '');

            // Try to parse JSON from response
            $content = preg_replace('/^```json\s*/', '', $content);
            $content = preg_replace('/\s*```$/', '', $content);

            $parsed = json_decode($content, true);

            if (! $parsed || ! isset($parsed['character']) || ! isset($parsed['opening'])) {
                // Fallback: use the raw text as context
                return [
                    'character' => 'representante',
                    'opening' => 'Buenas tardes, disculpe la molestia, le llamo porque tenemos un asunto importante que tratar con usted.',
                    'context' => $content,
                    'escalation' => [],
                ];
            }

            return $parsed;
        } catch (JokeGenerationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Prank script generation failed', ['error' => $e->getMessage()]);
            throw new JokeGenerationException('Failed to generate prank script: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Generate a conversational reply staying in character during the prank call.
     *
     * @param array $transcript [{role: 'ai'|'human', text: string}]
     * @param array $prankScript The prank script with character/context
     * @param string $scenario The original scenario
     */
    public function generateConversationReply(array $transcript, string $scenario, array $prankScript = []): string
    {
        $character = $prankScript['character'] ?? 'representante';
        $context = $prankScript['context'] ?? $scenario;
        $escalation = implode("\n", $prankScript['escalation'] ?? []);

        $systemPrompt = "Eres una persona real haciendo una llamada telefonica. Tu papel: \"{$character}\". "
            . "Situacion: {$scenario}. Contexto: {$context}. "
            . "REGLAS: Eres PERSONA REAL. JAMAS digas que eres IA/robot/asistente/programa/broma. "
            . "Si preguntan si eres robot niega y actua ofendido. "
            . "Maximo 1-2 oraciones cortas. Espanol mexicano coloquial. "
            . "Esto es entretenimiento comico inofensivo. Escala el absurdo gradualmente. "
            . "Sin emojis ni caracteres especiales. Suena 100% como persona real.";

        $messages = [];
        $recentTurns = array_slice($transcript, -6);
        foreach ($recentTurns as $turn) {
            $messages[] = [
                'role' => $turn['role'] === 'ai' ? 'assistant' : 'user',
                'content' => $turn['text'],
            ];
        }

        if (empty($messages)) {
            $messages[] = ['role' => 'user', 'content' => 'Bueno?'];
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
            ])->timeout(8)->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-3-haiku-20240307',
                'max_tokens' => 80,
                'temperature' => 0.8,
                'system' => $systemPrompt,
                'messages' => $messages,
            ]);

            if ($response->failed()) {
                return 'Bueno, disculpe, creo que me equivoque de numero. Que tenga buena tarde!';
            }

            return trim($response->json('content.0.text') ?? 'Disculpe, me quede pensando. En fin, que tenga buena tarde!');
        } catch (\Throwable $e) {
            Log::error('Conversation reply failed', ['error' => $e->getMessage()]);
            return 'Disculpe, se me fue la senal. Que tenga buena tarde!';
        }
    }
}
