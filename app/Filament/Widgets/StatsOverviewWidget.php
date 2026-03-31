<?php

namespace App\Filament\Widgets;

use App\Enums\JokeCallStatus;
use App\Models\JokeCall;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $today = now()->startOfDay();

        return [
            Stat::make('Calls Today', JokeCall::where('created_at', '>=', $today)->count())
                ->description('Total joke calls initiated today')
                ->color('success'),

            Stat::make('Completed', JokeCall::where('status', JokeCallStatus::Completed)->where('created_at', '>=', $today)->count())
                ->description('Successfully delivered today')
                ->color('info'),

            Stat::make('Revenue (est.)', '$' . number_format(
                JokeCall::where('status', JokeCallStatus::Completed)
                    ->where('created_at', '>=', $today)
                    ->sum('estimated_cost_usd') ?? 0,
                2
            ))
                ->description('Estimated cost today')
                ->color('warning'),

            Stat::make('Active Now', JokeCall::whereIn('status', [
                JokeCallStatus::Calling,
                JokeCallStatus::InProgress,
                JokeCallStatus::GeneratingJoke,
                JokeCallStatus::GeneratingAudio,
            ])->count())
                ->description('Calls in progress right now')
                ->color('danger'),
        ];
    }
}
