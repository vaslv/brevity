<?php

namespace App\Filament\Resources\Conditions\Pages;

use App\Filament\Resources\Conditions\ConditionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCondition extends ViewRecord
{
    protected static string $resource = ConditionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
