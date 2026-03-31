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
    protected static ?string $navigationLabel = 'Joke Calls';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('session_id')->disabled(),
            TextInput::make('phone_number')->disabled(),
            TextInput::make('joke_category'),
            TextInput::make('delivery_type'),
            TextInput::make('status')->disabled(),
            Textarea::make('joke_text')->rows(5),
            Textarea::make('failure_reason'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->limit(8)
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone_number')
                    ->formatStateUsing(fn(string $state) => substr($state, 0, 6) . '****' . substr($state, -2))
                    ->label('Phone'),
                Tables\Columns\TextColumn::make('joke_category')
                    ->badge()
                    ->label('Category'),
                Tables\Columns\TextColumn::make('delivery_type')
                    ->badge()
                    ->color(fn(string $state) => $state === 'whatsapp' ? 'success' : 'info'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(JokeCallStatus $state) => match (true) {
                        $state === JokeCallStatus::Completed => 'success',
                        $state === JokeCallStatus::Failed => 'danger',
                        $state === JokeCallStatus::Refunded => 'warning',
                        default => 'info',
                    }),
                Tables\Columns\TextColumn::make('reaction_sentiment')
                    ->badge()
                    ->color(fn(?string $state) => match ($state) {
                        'positive' => 'success',
                        'negative' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('call_duration_seconds')
                    ->label('Duration')
                    ->suffix('s'),
                Tables\Columns\TextColumn::make('estimated_cost_usd')
                    ->label('Cost')
                    ->prefix('$'),
                Tables\Columns\IconColumn::make('is_gift')
                    ->boolean()
                    ->label('Gift'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(JokeCallStatus::cases())->mapWithKeys(
                        fn($case) => [$case->value => $case->label()]
                    )),
                Tables\Filters\SelectFilter::make('joke_category')
                    ->options([
                        'general' => 'General',
                        'dad' => 'Papa',
                        'dark' => 'Dark',
                        'adulto' => 'Adulto',
                        'political' => 'Political',
                    ]),
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
