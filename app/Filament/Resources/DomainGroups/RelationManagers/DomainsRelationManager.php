<?php

namespace App\Filament\Resources\DomainGroups\RelationManagers;

use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
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
            ->recordTitleAttribute('value')
            ->emptyStateHeading(__('resources/domain-group.fields.domains_empty'))
            ->columns([
                TextColumn::make('value')
                    ->label(__('resources/domain.fields.value'))
                    ->searchable(),
            ])
            ->recordActions([
                DetachAction::make()
                    ->label(__('resources/domain-group.actions.detach_domain.label'))
                    ->modalDescription(__('resources/domain-group.actions.detach_domain.hint')),
            ]);
    }
}
