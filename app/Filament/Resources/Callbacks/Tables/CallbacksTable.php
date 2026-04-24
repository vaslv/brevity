<?php

namespace App\Filament\Resources\Callbacks\Tables;

use App\Services\Links\Callbacks\CallbackStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CallbacksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('service.name')
                    ->label(__('resources/callback.fields.service'))
                    ->searchable(),
                TextColumn::make('click_id')
                    ->label(__('resources/callback.fields.click_id'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('response_code')
                    ->label(__('resources/callback.fields.response_code'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('resources/callback.fields.status'))
                    ->formatStateUsing(fn (CallbackStatus|string|null $state) => $state
                        ? __('resources/callback.statuses.'.($state instanceof CallbackStatus ? $state->value : $state))
                        : null)
                    ->searchable(),
                TextColumn::make('attempts')
                    ->label(__('resources/callback.fields.attempts'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_attempt_at')
                    ->label(__('resources/callback.fields.last_attempt_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('resources/callback.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
