<?php

namespace App\Filament\Resources\DomainGroups\Pages;

use App\Filament\Resources\DomainGroups\DomainGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDomainGroups extends ListRecords
{
    protected static string $resource = DomainGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
