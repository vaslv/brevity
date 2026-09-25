<?php

namespace App\Filament\Resources\DomainGroups\RelationManagers;

use App\Models\Domain;
use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DomainsRelationManager extends RelationManager
{
    protected static string $relationship = 'domains';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('resources/domain-group.fields.domains');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('display_domain')
            ->emptyStateHeading(__('resources/domain-group.fields.domains_empty'))
            ->columns([
                TextColumn::make('value')
                    ->label(__('resources/domain.fields.value'))
                    ->formatStateUsing(fn (Domain $record): string => $record->display_domain)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereIn('domains.id', Domain::query()->matchingName($search)->select('id'))),
            ])
            ->recordActions([
                DetachAction::make()
                    ->label(__('resources/domain-group.actions.detach_domain.label'))
                    ->modalDescription(__('resources/domain-group.actions.detach_domain.hint')),
            ]);
    }
}
