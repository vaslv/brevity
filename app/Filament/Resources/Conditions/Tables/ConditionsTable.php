<?php

namespace App\Filament\Resources\Conditions\Tables;

use App\Filament\Support\RestrictedDeleteBulkAction;
use App\Services\Links\Conditions\ConditionRegistry;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ConditionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label(__('resources/condition.fields.type'))
                    ->formatStateUsing(fn (string $state): string => __('resources/condition.types.'.$state))
                    // Search by the translated type label, not the raw registry key.
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereIn(
                        'type',
                        self::typesMatching($search),
                    ))
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
                    RestrictedDeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Registered condition types whose translated label contains the search
     * term, so the type column searches by its visible label.
     *
     * @return list<string>
     */
    private static function typesMatching(string $search): array
    {
        $needle = Str::lower($search);

        return collect(app(ConditionRegistry::class)->types())
            ->filter(fn (string $type): bool => str_contains(
                Str::lower(__('resources/condition.types.'.$type)),
                $needle,
            ))
            ->values()
            ->all();
    }
}
