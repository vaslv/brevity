<?php

namespace App\Filament\Resources\Clicks\Pages;

use App\Filament\Resources\Clicks\ClickResource;
use Filament\Resources\Pages\ViewRecord;

class ViewClick extends ViewRecord
{
    protected static string $resource = ClickResource::class;

    public function getTitle(): string
    {
        return __('resources/click.pages.view_title');
    }
}
