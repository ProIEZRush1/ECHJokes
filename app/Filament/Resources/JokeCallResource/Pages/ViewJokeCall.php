<?php

namespace App\Filament\Resources\JokeCallResource\Pages;

use App\Enums\JokeCallStatus;
use App\Filament\Resources\JokeCallResource;
use Filament\Resources\Pages\ViewRecord;

class ViewJokeCall extends ViewRecord
{
    protected static string $resource = JokeCallResource::class;

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();
        if ($record->status === JokeCallStatus::InProgress || $record->status === JokeCallStatus::Calling) {
            return 'LIVE';
        }
        return null;
    }

    protected function getFooterWidgets(): array
    {
        return [
            JokeCallResource\Widgets\LiveTranscriptWidget::class,
        ];
    }

    public function getFooterWidgetColumns(): int|array
    {
        return 1;
    }
}
