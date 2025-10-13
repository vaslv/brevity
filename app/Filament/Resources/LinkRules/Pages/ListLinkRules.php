<?php

namespace App\Filament\Resources\LinkRules\Pages;

use App\Filament\Resources\LinkRules\LinkRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLinkRules extends ListRecords
{
    protected static string $resource = LinkRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
