<?php

namespace App\Filament\Resources\UserAgents\Pages;

use App\Filament\Resources\UserAgents\UserAgentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUserAgents extends ListRecords
{
    protected static string $resource = UserAgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
