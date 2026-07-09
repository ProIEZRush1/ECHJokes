<?php

namespace App\Services;

use App\Models\JokeCall;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AssistantSummaryService
{
    /**
     * Produce a short Spanish recap of what happened on an assistant call, from
     * its live transcript. Returns null if there's nothing to summarize or the
     * model call fails (the caller just skips the summary in that case).
     */
    public function generate(JokeCall $jokeCall): ?string
    {
        $turns = json_decode((string) $jokeCall->live_transcript, true);
        if (! is_array($turns) || count($turns) === 0) {
            return null;
        }

        $lines = [];
        foreach ($turns as $t) {
            $role = $t['role'] ?? '';
            $text = trim((string) ($t['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $label = match ($role) {
                'ai'       => 'IA',
                'human'    => 'EMPRESA',
                'question' => 'IA PREGUNTA AL OPERADOR',
                'answer'   => 'OPERADOR RESPONDE',
                'dtmf'     => 'IA MARCA TECLAS',
                'system'   => 'SISTEMA',
                default    => strtoupper($role),
            };
            $lines[] = "{$label}: {$text}";
        }
        if (empty($lines)) {
            return null;
        }
        $transcript = implode("\n", $lines);

        $system = 'Eres un asistente que resume una llamada telefónica que hizo una IA en nombre de un cliente. '
            . 'Con base en la transcripción, escribe un RESUMEN BREVE en español para el operador humano. Incluye: '
            . '(1) el ESTADO del objetivo (¿se cumplió, quedó parcial, o no se logró?), '
            . '(2) lo más importante que pasó o que dijo la empresa, '
            . '(3) qué queda PENDIENTE o el siguiente paso, si aplica. '
            . 'Máximo 4 oraciones, claro y directo. NO inventes nada que no esté en la transcripción. '
            . 'Escribe solo el resumen, sin encabezados ni viñetas.';

        $user = "OBJETIVO DE LA LLAMADA:\n" . (string) ($jokeCall->assistant_objective ?: $jokeCall->custom_joke_prompt ?: '(no especificado)')
            . "\n\nTRANSCRIPCIÓN:\n" . $transcript;

        try {
            $r = Http::withHeaders([
                'x-api-key' => config('services.anthropic.api_key'),
                'anthropic-version' => '2023-06-01',
            ])->timeout(12)->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-haiku-4-5-20251001',
                'max_tokens' => 350,
                'temperature' => 0.3,
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => $user]],
            ]);

            $summary = trim((string) $r->json('content.0.text'));
            return $summary !== '' ? $summary : null;
        } catch (\Throwable $e) {
            Log::warning('Assistant summary generation failed', [
                'call_id' => $jokeCall->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
