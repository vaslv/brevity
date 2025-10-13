<?php

namespace App\Filament\Resources\LinkRules\Pages;

use App\Filament\Resources\LinkRules\LinkRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditLinkRule extends EditRecord
{
    protected static string $resource = LinkRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
