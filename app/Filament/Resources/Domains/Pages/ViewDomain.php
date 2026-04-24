<?php

namespace App\Filament\Resources\Domains\Pages;

use App\Filament\Resources\Domains\DomainResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDomain extends ViewRecord
{
    protected static string $resource = DomainResource::class;

    public function getTitle(): string
    {
        return __('resources/domain.pages.view_title');
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
