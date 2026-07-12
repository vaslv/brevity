<?php

namespace App\Filament\Resources\Links\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LinksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Second aggregate for the clicks column description: the main
            // state (total) is loaded by the column's ->sum() itself.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withSum(
                ['clickCounters as non_bot_clicks_sum' => fn ($q) => $q->where('is_bot', false)],
                'count',
            ))
            ->columns([
                TextColumn::make('service.name')
                    ->label(__('resources/link.fields.service'))
                    ->searchable(),
                TextColumn::make('url')
                    ->label(__('resources/link.fields.short_url'))
                    ->copyable()
                    ->copyMessage(__('resources/link.fields.short_url_copied'))
                    ->searchable(query: fn ($query, string $search) => $query->where('code', 'ilike', "%{$search}%")),
                // Click totals come from the pre-aggregated slot counters, not
                // COUNT over clicks (see docs/01-architecture.md) — the clicks
                // table grows unbounded, the counters stay ~200 rows per link max.
                TextColumn::make('click_counters_sum_count')
                    ->label(__('resources/link.fields.clicks_count'))
                    ->sum('clickCounters', 'count')
                    ->default(0)
                    ->formatStateUsing(fn ($state): string => (string) (int) $state)
                    ->description(fn ($record): string => __('resources/link.fields.clicks_count_non_bots', [
                        'count' => (int) $record->non_bot_clicks_sum,
                    ]))
                    // A link without counters aggregates to NULL, and Postgres
                    // puts NULLs first on DESC — a zero-click link would top the
                    // "most clicked" sort. Treat NULL as zero explicitly.
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                        'click_counters_sum_count '.($direction === 'desc' ? 'desc nulls last' : 'asc nulls first'),
                    )),
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
                // "Alive only": inside the lifecycle window and under the
                // click limit — the same semantics the resolver enforces.
                Filter::make('only_alive')
                    ->label(__('resources/link.filters.only_alive'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->where(fn (Builder $q) => $q->whereNull('valid_since')->orWhere('valid_since', '<=', now()))
                        ->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()))
                        ->where(fn (Builder $q) => $q
                            ->whereNull('max_clicks')
                            ->orWhereRaw('(select coalesce(sum(count), 0) from link_click_counters where link_id = links.id) < links.max_clicks'))),
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
