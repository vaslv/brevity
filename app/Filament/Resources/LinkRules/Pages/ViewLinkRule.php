<?php

namespace App\Filament\Resources\LinkRules\Pages;

use App\Filament\Resources\LinkRules\LinkRuleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLinkRule extends ViewRecord
{
    protected static string $resource = LinkRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
