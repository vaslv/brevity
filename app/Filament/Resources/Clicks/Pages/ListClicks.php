<?php

namespace App\Filament\Resources\Clicks\Pages;

use App\Filament\Resources\Clicks\ClickResource;
use App\Filament\Widgets\ClicksChart;
use Filament\Resources\Pages\ListRecords;

class ListClicks extends ListRecords
{
    protected static string $resource = ClickResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ClicksChart::class,
        ];
    }
}
