<?php

namespace App\Filament\Resources\Urls\Pages;

use App\Filament\Resources\Urls\UrlResource;
use Filament\Resources\Pages\ViewRecord;

class ViewUrl extends ViewRecord
{
    protected static string $resource = UrlResource::class;

    public function getTitle(): string
    {
        return __('resources/url.pages.view_title');
    }
}
