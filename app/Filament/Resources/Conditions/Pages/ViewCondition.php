<?php

namespace App\Filament\Resources\Conditions\Pages;

use App\Filament\Resources\Conditions\ConditionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCondition extends ViewRecord
{
    protected static string $resource = ConditionResource::class;

    public function getTitle(): string
    {
        return __('resources/condition.pages.view_title');
    }
}
