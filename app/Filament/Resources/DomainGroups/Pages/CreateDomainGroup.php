<?php

namespace App\Filament\Resources\DomainGroups\Pages;

use App\Filament\Resources\DomainGroups\DomainGroupResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDomainGroup extends CreateRecord
{
    protected static string $resource = DomainGroupResource::class;

    public function getTitle(): string
    {
        return __('resources/domain-group.pages.create_title');
    }
}
