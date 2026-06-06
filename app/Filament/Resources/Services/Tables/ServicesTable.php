<?php

namespace App\Filament\Resources\Services\Tables;

use App\Filament\Support\RestrictedDeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('resources/service.fields.name'))
                    ->searchable(),
                TextColumn::make('callback_url')
                    ->label(__('resources/service.fields.callback_url'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('resources/service.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    RestrictedDeleteBulkAction::make(),
                ]),
            ]);
    }
}
