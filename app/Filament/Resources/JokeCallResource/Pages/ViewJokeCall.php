<?php

namespace App\Filament\Resources\JokeCallResource\Pages;

use App\Filament\Resources\JokeCallResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\ViewEntry;

class ViewJokeCall extends ViewRecord
{
    protected static string $resource = JokeCallResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Call Details')->schema([
                TextEntry::make('phone_number')->label('Phone'),
                TextEntry::make('custom_joke_prompt')->label('Scenario'),
                TextEntry::make('status')->badge(),
                TextEntry::make('twilio_call_sid')->label('Twilio SID'),
                TextEntry::make('call_duration_seconds')->label('Duration')->suffix('s'),
                TextEntry::make('created_at')->dateTime(),
            ])->columns(2),

            Section::make('Recording')->schema([
                TextEntry::make('recording_url')
                    ->label('Recording')
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) return 'No recording available';
                        return new \Illuminate\Support\HtmlString(
                            '<audio controls src="' . e($state) . '" style="width:100%"></audio>'
                            . '<br><a href="' . e($state) . '" target="_blank" class="text-sm text-blue-500">Download</a>'
                        );
                    })
                    ->html(),
            ]),

            Section::make('AI Response')->schema([
                TextEntry::make('joke_text')->label('Opening Line'),
                TextEntry::make('failure_reason')->label('Error')->visible(fn($record) => !empty($record->failure_reason)),
            ]),
        ]);
    }
}
