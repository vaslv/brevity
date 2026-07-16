<?php

namespace App\Filament\Resources\Clicks\Tables;

use App\Models\GeoLocation;
use App\Models\Service;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
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
                // Search is limited to link.code and ipAddress.value (r46): a
                // searchable relation column compiles to an OR'd whereHas with a
                // leading-wildcard ILIKE, and fanning that across six relations on
                // the unbounded clicks table is a self-inflicted DoS at scale.
                // Service and bot have dedicated filters instead.
                TextColumn::make('service.name')
                    ->label(__('resources/click.fields.service')),
                TextColumn::make('link.code')
                    ->label(__('resources/click.fields.link'))
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('url.value')
                    ->label(__('resources/click.fields.url'))
                    ->limit(50)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('referrer.value')
                    ->label(__('resources/click.fields.referrer'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('userAgent.value')
                    ->label(__('resources/click.fields.user_agent'))
                    ->limit(60)
                    ->tooltip(fn (?string $state): ?string => $state),
                // A click without a user agent has no flag to compute → not a bot.
                IconColumn::make('userAgent.is_bot')
                    ->label(__('resources/click.fields.is_bot'))
                    ->boolean()
                    ->default(false),
                TextColumn::make('ipAddress.value')
                    ->label(__('resources/click.fields.ip_address'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('geoLocation.country_code')
                    ->label(__('resources/click.fields.country'))
                    ->placeholder('-'),
                TextColumn::make('geoLocation.region')
                    ->label(__('resources/click.fields.region'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('geoLocation.city')
                    ->label(__('resources/click.fields.city'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('service_id')
                    ->label(__('resources/click.filters.service'))
                    ->options(fn () => Service::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
                SelectFilter::make('link_id')
                    ->label(__('resources/click.filters.link'))
                    // Lazy server-side search instead of loading every link.
                    ->relationship('link', 'code')
                    ->searchable(),
                TernaryFilter::make('is_bot')
                    ->label(__('resources/click.filters.is_bot'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas(
                            'userAgent',
                            fn (Builder $userAgent) => $userAgent->where('is_bot', true),
                        ),
                        // "Not a bot" includes clicks without a user agent.
                        false: fn (Builder $query): Builder => $query->whereDoesntHave(
                            'userAgent',
                            fn (Builder $userAgent) => $userAgent->where('is_bot', true),
                        ),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                // Options are distinct codes from the geo dictionary;
                // ->relationship() would list one option per country/region/city
                // tuple, duplicating every country.
                SelectFilter::make('country')
                    ->label(__('resources/click.filters.country'))
                    ->options(fn () => GeoLocation::query()
                        ->distinct()
                        ->orderBy('country_code')
                        ->pluck('country_code', 'country_code'))
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['value'] ?? null, fn (Builder $q, string $country) => $q->whereHas(
                            'geoLocation',
                            fn (Builder $geo) => $geo->where('country_code', $country),
                        ))),
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
