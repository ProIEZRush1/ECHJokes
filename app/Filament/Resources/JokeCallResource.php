<?php

namespace App\Filament\Resources;

use App\Enums\JokeCallStatus;
use App\Models\JokeCall;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class JokeCallResource extends Resource
{
    protected static ?string $model = JokeCall::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-phone';
    protected static ?string $navigationLabel = 'Calls';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('session_id')->disabled(),
            TextInput::make('phone_number')->disabled(),
            TextInput::make('custom_joke_prompt')->label('Scenario'),
            TextInput::make('status')->disabled(),
            TextInput::make('twilio_call_sid')->label('Call SID')->disabled(),
            Textarea::make('failure_reason')->rows(2),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Call Details')
                ->icon('heroicon-o-phone')
                ->components([
                    Grid::make(3)->components([
                        TextEntry::make('phone_number')
                            ->label('Phone')
                            ->icon('heroicon-o-phone'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn(JokeCallStatus $state) => match (true) {
                                $state === JokeCallStatus::Completed => 'success',
                                $state === JokeCallStatus::Failed => 'danger',
                                default => 'info',
                            }),
                        TextEntry::make('created_at')
                            ->label('Date')
                            ->dateTime('M j, Y H:i')
                            ->icon('heroicon-o-clock'),
                    ]),
                    Grid::make(3)->components([
                        TextEntry::make('call_duration_seconds')
                            ->label('Duration')
                            ->formatStateUsing(fn($state) => $state ? gmdate('i:s', (int) $state) : '-')
                            ->icon('heroicon-o-clock'),
                        TextEntry::make('twilio_call_sid')
                            ->label('Call SID')
                            ->copyable()
                            ->icon('heroicon-o-finger-print'),
                        TextEntry::make('session_id')
                            ->label('Session')
                            ->copyable()
                            ->icon('heroicon-o-hashtag'),
                    ]),
                ]),

            Section::make('Scenario')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->components([
                    TextEntry::make('custom_joke_prompt')
                        ->label('')
                        ->columnSpanFull(),
                ]),

            Section::make('Recording')
                ->icon('heroicon-o-microphone')
                ->visible(fn($record) => !empty($record->recording_url))
                ->components([
                    ViewEntry::make('recording_player')
                        ->label('')
                        ->view('filament.components.audio-player')
                        ->columnSpanFull(),
                ]),

            Section::make('Error')
                ->icon('heroicon-o-exclamation-triangle')
                ->visible(fn($record) => !empty($record->failure_reason))
                ->components([
                    TextEntry::make('failure_reason')
                        ->label('')
                        ->color('danger')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->limit(8)->searchable(),
                Tables\Columns\TextColumn::make('phone_number')
                    ->formatStateUsing(fn(string $state) => substr($state, 0, 6) . '****' . substr($state, -2))
                    ->label('Phone'),
                Tables\Columns\TextColumn::make('custom_joke_prompt')
                    ->label('Scenario')
                    ->limit(40),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(JokeCallStatus $state) => match (true) {
                        $state === JokeCallStatus::Completed => 'success',
                        $state === JokeCallStatus::Failed => 'danger',
                        $state === JokeCallStatus::Voicemail => 'warning',
                        default => 'info',
                    }),
                Tables\Columns\TextColumn::make('call_duration_seconds')
                    ->label('Duration')
                    ->formatStateUsing(fn($state) => $state ? gmdate('i:s', (int) $state) : '-'),
                Tables\Columns\IconColumn::make('recording_url')
                    ->label('Rec')
                    ->boolean()
                    ->getStateUsing(fn($record) => !empty($record->recording_url)),
                Tables\Columns\TextColumn::make('created_at')->dateTime('M j, H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(JokeCallStatus::cases())->mapWithKeys(
                        fn($case) => [$case->value => $case->label()]
                    )),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\JokeCallResource\Pages\ListJokeCalls::route('/'),
            'view' => \App\Filament\Resources\JokeCallResource\Pages\ViewJokeCall::route('/{record}'),
            'edit' => \App\Filament\Resources\JokeCallResource\Pages\EditJokeCall::route('/{record}/edit'),
        ];
    }
}
