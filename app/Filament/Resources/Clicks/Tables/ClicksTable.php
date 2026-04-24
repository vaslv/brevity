<?php

namespace App\Filament\Resources\Clicks\Tables;

use App\Models\Link;
use App\Models\Service;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClicksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('resources/click.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('service.name')
                    ->label(__('resources/click.fields.service'))
                    ->searchable(),
                TextColumn::make('link.code')
                    ->label(__('resources/click.fields.link'))
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('url.value')
                    ->label(__('resources/click.fields.url'))
                    ->limit(50)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('referrer.value')
                    ->label(__('resources/click.fields.referrer'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('userAgent.value')
                    ->label(__('resources/click.fields.user_agent'))
                    ->limit(60)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->searchable(),
                TextColumn::make('ipAddress.value')
                    ->label(__('resources/click.fields.ip_address'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('service_id')
                    ->label(__('resources/click.filters.service'))
                    ->options(fn () => Service::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
                SelectFilter::make('link_id')
                    ->label(__('resources/click.filters.link'))
                    ->options(fn () => Link::query()->orderBy('code')->pluck('code', 'id'))
                    ->searchable(),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('created_from')
                            ->label(__('resources/click.filters.created_from')),
                        DatePicker::make('created_until')
                            ->label(__('resources/click.filters.created_until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['created_from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['created_until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date))),
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
