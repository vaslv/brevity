<?php

namespace App\Filament\Resources\Links\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class LinksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('service.name')
                    ->label(__('resources/link.fields.service'))
                    ->searchable(),
                TextColumn::make('url')
                    ->label(__('resources/link.fields.short_url'))
                    ->copyable()
                    ->copyMessage(__('resources/link.fields.short_url_copied'))
                    ->searchable(query: fn ($query, string $search) => $query->where('code', 'ilike', "%{$search}%")),
                TextColumn::make('clicks_count')
                    ->label(__('resources/link.fields.clicks_count'))
                    ->counts('clicks')
                    ->sortable(),
                IconColumn::make('forward_query')
                    ->label(__('resources/link.fields.forward_query'))
                    ->boolean()
                    ->tooltip(fn (bool $state): string => $state
                        ? __('resources/link.fields.forward_query_yes')
                        : __('resources/link.fields.forward_query_no')),
                TextColumn::make('domain.value')
                    ->label(__('resources/link.fields.domain'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('code')
                    ->label(__('resources/link.fields.code'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('title')
                    ->label(__('resources/link.fields.title'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('resources/link.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label(__('resources/link.fields.deleted_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // No ForceDelete: clicks (restrictOnDelete) must outlive the
                    // link; soft delete is the disable mechanism, Restore the undo.
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
