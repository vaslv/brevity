<?php

namespace App\Filament\Resources\DomainGroups\Pages;

use App\Filament\Resources\DomainGroups\DomainGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDomainGroup extends EditRecord
{
    protected static string $resource = DomainGroupResource::class;

    public function getTitle(): string
    {
        return __('resources/domain-group.pages.edit_title');
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
