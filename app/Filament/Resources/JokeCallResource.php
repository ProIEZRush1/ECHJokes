<?php

namespace App\Filament\Resources;

use App\Enums\JokeCallStatus;
use App\Models\JokeCall;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\TextInput;
use Filament\Schemas\Components\Textarea;
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
            TextInput::make('recording_url')->label('Recording URL')->disabled(),
            Textarea::make('joke_text')->label('Opening Line')->rows(3),
            Textarea::make('failure_reason')->rows(2),
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
                        default => 'info',
                    }),
                Tables\Columns\TextColumn::make('call_duration_seconds')->label('Duration')->suffix('s'),
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
