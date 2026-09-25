<?php

namespace App\Filament\Resources\Domains\Tables;

use App\Filament\Support\RestrictedDeleteBulkAction;
use App\Models\Domain;
use App\Models\DomainGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class DomainsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('value')
                    ->label(__('resources/domain.fields.value'))
                    ->formatStateUsing(fn (Domain $record): string => $record->display_domain)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereIn('domains.id', Domain::query()->matchingName($search)->select('id'))),
                IconColumn::make('is_default')
                    ->label(__('resources/domain.fields.is_default'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('resources/domain.fields.created_at'))
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
                    BulkAction::make('attachToGroup')
                        ->label(__('resources/domain.actions.attach_to_group.label'))
                        ->modalHeading(__('resources/domain.actions.attach_to_group.label'))
                        ->modalSubmitActionLabel(__('resources/domain.actions.attach_to_group.label'))
                        ->schema([
                            Select::make('group_id')
                                ->label(__('resources/domain.actions.attach_to_group.group'))
                                ->options(fn (): array => DomainGroup::query()->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->required()
                                ->exists(DomainGroup::class, 'id'),
                        ])
                        ->databaseTransaction()
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle(__('resources/domain.actions.attach_to_group.notification'))
                        ->action(function (array $data, Collection $records, BulkAction $action): void {
                            $group = DomainGroup::query()->findOrFail($data['group_id']);
                            $group->domains()->syncWithoutDetaching($records->modelKeys());

                            $action->success();
                        }),
                    RestrictedDeleteBulkAction::make(),
                ]),
            ]);
    }
}
