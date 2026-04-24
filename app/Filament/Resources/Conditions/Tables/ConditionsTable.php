<?php

namespace App\Filament\Resources\Conditions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConditionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label(__('resources/condition.fields.type'))
                    ->formatStateUsing(fn (string $state): string => __('resources/condition.types.'.$state))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('data')
                    ->label(__('resources/condition.fields.data'))
                    ->formatStateUsing(fn ($state): string => json_encode($state, JSON_UNESCAPED_UNICODE))
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label(__('resources/condition.fields.created_at'))
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
