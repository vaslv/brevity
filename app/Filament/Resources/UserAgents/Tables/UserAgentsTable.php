<?php

namespace App\Filament\Resources\UserAgents\Tables;

use App\Filament\Support\RestrictedDeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UserAgentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('value')
                    ->label(__('resources/user_agent.fields.value'))
                    ->searchable(),
                IconColumn::make('is_bot')
                    ->label(__('resources/user_agent.fields.is_bot'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('resources/user_agent.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_bot')
                    ->label(__('resources/user_agent.filters.is_bot')),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    RestrictedDeleteBulkAction::make(),
                ]),
            ]);
    }
}
