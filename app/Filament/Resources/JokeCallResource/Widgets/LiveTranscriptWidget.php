<?php

namespace App\Filament\Resources\JokeCallResource\Widgets;

use App\Enums\JokeCallStatus;
use App\Models\JokeCall;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class LiveTranscriptWidget extends Widget
{
    protected string $view = 'filament.widgets.live-transcript';

    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    public function getTranscript(): array
    {
        if (!$this->record) return [];

        $this->record->refresh();
        $data = $this->record->live_transcript;
        if (!$data) return [];

        return json_decode($data, true) ?: [];
    }

    public function isLive(): bool
    {
        if (!$this->record) return false;
        $this->record->refresh();
        return in_array($this->record->status, [JokeCallStatus::Calling, JokeCallStatus::InProgress]);
    }

    protected function getPollingInterval(): ?string
    {
        return $this->isLive() ? '2s' : null;
    }
}
