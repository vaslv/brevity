<?php

namespace App\Filament\Resources\Domains\RelationManagers;

use App\Models\DomainGroup;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DomainGroupsRelationManager extends RelationManager
{
    protected static string $relationship = 'domainGroups';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('resources/domain.groups.title');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->emptyStateHeading(__('resources/domain.groups.empty'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('resources/domain-group.fields.name'))
                    ->searchable(),
                TextColumn::make('code')
                    ->label(__('resources/domain-group.fields.code'))
                    ->badge()
                    ->searchable(),
            ])
            ->headerActions([
                AttachAction::make('attachToGroup')
                    ->label(__('resources/domain.actions.attach_to_group.label'))
                    ->modalHeading(__('resources/domain.actions.attach_to_group.label'))
                    ->modalSubmitActionLabel(__('resources/domain.actions.attach_to_group.label'))
                    ->successNotificationTitle(__('resources/domain.actions.attach_to_group.notification'))
                    ->recordSelect(fn (Select $select): Select => $select
                        ->label(__('resources/domain.actions.attach_to_group.group'))
                        ->hiddenLabel(false))
                    ->preloadRecordSelect()
                    ->attachAnother(false)
                    ->using(function (DomainGroup $record, BelongsToMany $relationship): void {
                        $relationship->syncWithoutDetaching([$record->getKey()]);
                    }),
            ])
            ->recordActions([
                DetachAction::make()
                    ->label(__('resources/domain.groups.detach'))
                    ->modalDescription(__('resources/domain.groups.detach_hint')),
            ]);
    }
}
