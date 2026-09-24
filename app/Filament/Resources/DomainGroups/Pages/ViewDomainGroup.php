<?php

namespace App\Filament\Resources\DomainGroups\Pages;

use App\Filament\Resources\DomainGroups\DomainGroupResource;
use App\Filament\Resources\DomainGroups\RelationManagers\DomainsRelationManager;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDomainGroup extends ViewRecord
{
    protected static string $resource = DomainGroupResource::class;

    public function getTitle(): string
    {
        return __('resources/domain-group.pages.view_title');
    }

    protected function getAllRelationManagers(): array
    {
        return [DomainsRelationManager::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
