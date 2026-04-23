<?php

namespace App\Filament\Resources\Services\RelationManagers;

use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TokensRelationManager extends RelationManager
{
    protected static string $relationship = 'tokens';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('resources/service.tokens.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('resources/service.tokens.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('abilities')
                    ->label(__('resources/service.tokens.fields.abilities'))
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : (string) $state)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_used_at')
                    ->label(__('resources/service.tokens.fields.last_used_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('resources/service.tokens.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->fetchSelectedRecords(false),
            ]);
    }
}
