<?php

namespace App\Filament\Resources\Clicks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClicksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('service.name')
                    ->label(__('resources/click.fields.service'))
                    ->searchable(),
                TextColumn::make('url.value')
                    ->label(__('resources/click.fields.url'))
                    ->searchable(),
                TextColumn::make('link.title')
                    ->label(__('resources/click.fields.link'))
                    ->searchable(),
                TextColumn::make('referrer.value')
                    ->label(__('resources/click.fields.referrer'))
                    ->searchable(),
                TextColumn::make('userAgent.value')
                    ->label(__('resources/click.fields.user_agent'))
                    ->searchable(),
                TextColumn::make('ipAddress.value')
                    ->label(__('resources/click.fields.ip_address'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('resources/click.fields.created_at'))
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
