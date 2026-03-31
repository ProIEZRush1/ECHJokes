<?php

namespace App\Filament\Widgets;

use App\Models\JokeCall;
use Filament\Widgets\ChartWidget;

class SentimentChartWidget extends ChartWidget
{
    protected ?string $heading = 'Reaction Sentiment';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $sentiments = JokeCall::whereNotNull('reaction_sentiment')
            ->selectRaw('reaction_sentiment, count(*) as count')
            ->groupBy('reaction_sentiment')
            ->pluck('count', 'reaction_sentiment')
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Reactions',
                    'data' => [
                        $sentiments['positive'] ?? 0,
                        $sentiments['neutral'] ?? 0,
                        $sentiments['negative'] ?? 0,
                    ],
                    'backgroundColor' => ['#39FF14', '#666666', '#FF4444'],
                ],
            ],
            'labels' => ['Positive', 'Neutral', 'Negative'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
