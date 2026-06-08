<?php

namespace App\Filament\Resources\Callbacks\Tables;

use App\Services\Links\Callbacks\CallbackStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CallbacksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('service.name')
                    ->label(__('resources/callback.fields.service'))
                    ->searchable(),
                TextColumn::make('click_id')
                    ->label(__('resources/callback.fields.click_id'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('response_code')
                    ->label(__('resources/callback.fields.response_code'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('resources/callback.fields.status'))
                    ->formatStateUsing(fn (CallbackStatus|string|null $state) => $state
                        ? __('resources/callback.statuses.'.($state instanceof CallbackStatus ? $state->value : $state))
                        : null)
                    // Search by the translated label the user sees, not the raw
                    // enum value stored in the column.
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereIn(
                        'status',
                        self::statusValuesMatching($search),
                    )),
                TextColumn::make('attempts')
                    ->label(__('resources/callback.fields.attempts'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_attempt_at')
                    ->label(__('resources/callback.fields.last_attempt_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('resources/callback.fields.created_at'))
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

    /**
     * Backing values of CallbackStatus whose translated label contains the
     * search term, so the status column searches by its visible label.
     *
     * @return list<string>
     */
    private static function statusValuesMatching(string $search): array
    {
        $needle = Str::lower($search);

        return collect(CallbackStatus::cases())
            ->filter(fn (CallbackStatus $status): bool => str_contains(
                Str::lower(__('resources/callback.statuses.'.$status->value)),
                $needle,
            ))
            ->map(fn (CallbackStatus $status): string => $status->value)
            ->values()
            ->all();
    }
}
