<?php

namespace App\Filament\Resources\Urls\Tables;

use App\Filament\Support\RestrictedDeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UrlsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('value')
                    ->label(__('resources/url.fields.value'))
                    ->searchable(),
                TextColumn::make('rules_count')
                    ->label(__('resources/url.fields.rules_count'))
                    ->counts('rules')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('resources/url.fields.created_at'))
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
                    RestrictedDeleteBulkAction::make(),
                ]),
            ]);
    }
}
