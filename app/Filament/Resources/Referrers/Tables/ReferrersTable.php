<?php

namespace App\Filament\Resources\Referrers\Tables;

use App\Filament\Support\RestrictedDeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReferrersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('value')
                    ->label(__('resources/referrer.fields.value'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('resources/referrer.fields.created_at'))
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
