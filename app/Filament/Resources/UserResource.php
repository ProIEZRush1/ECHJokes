<?php

namespace App\Filament\Resources;

use App\Models\User;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\TextInput;
use Filament\Schemas\Components\Toggle;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Users';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name'),
            TextInput::make('email')->email(),
            TextInput::make('phone'),
            Toggle::make('is_admin'),
            TextInput::make('subscription_plan'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('subscription_plan')
                    ->badge()
                    ->default('free')
                    ->color(fn(?string $state) => match ($state) {
                        'bromista' => 'info',
                        'comediante' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('credit.credits_remaining')
                    ->label('Credits')
                    ->default(0),
                Tables\Columns\TextColumn::make('joke_calls_count')
                    ->counts('jokeCalls')
                    ->label('Total Calls'),
                Tables\Columns\IconColumn::make('is_admin')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->dateTime('M j, Y'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                \Filament\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\UserResource\Pages\ListUsers::route('/'),
            'edit' => \App\Filament\Resources\UserResource\Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
