<?php

namespace App\Filament\Resources\UserAgents\Pages;

use App\Filament\Resources\UserAgents\UserAgentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUserAgent extends ViewRecord
{
    protected static string $resource = UserAgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
