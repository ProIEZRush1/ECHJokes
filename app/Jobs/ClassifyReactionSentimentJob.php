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

class ClassifyReactionSentimentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;

    public function __construct(
        public JokeCall $jokeCall,
    ) {
        $this->onQueue('recordings');
    }

    public function handle(): void
    {
        $transcript = $this->jokeCall->ai_transcript;
        if (empty($transcript)) {
            return;
        }

        $humanResponses = collect($transcript)
            ->where('role', 'human')
            ->pluck('text')
            ->join(' ');

        if (empty($humanResponses)) {
            $this->jokeCall->update(['reaction_sentiment' => 'neutral']);
            return;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.api_key'),
                'anthropic-version' => '2023-06-01',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-sonnet-4-20250514',
                'max_tokens' => 10,
                'temperature' => 0,
                'system' => 'Classify the sentiment of this phone call reaction to a joke. Respond with exactly one word: positive, negative, or neutral.',
                'messages' => [
                    ['role' => 'user', 'content' => $humanResponses],
                ],
            ]);

            $sentiment = strtolower(trim($response->json('content.0.text') ?? 'neutral'));

            if (! in_array($sentiment, ['positive', 'negative', 'neutral'])) {
                $sentiment = 'neutral';
            }

            $this->jokeCall->update(['reaction_sentiment' => $sentiment]);
        } catch (\Throwable $e) {
            Log::error('Sentiment classification failed', ['error' => $e->getMessage()]);
        }
    }
}
