<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use App\Filament\Support\RestrictedDeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditService extends EditRecord
{
    protected static string $resource = ServiceResource::class;

    public function getTitle(): string
    {
        return __('resources/service.pages.edit_title');
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            RestrictedDeleteAction::make(),
        ];
    }
}
