<?php

namespace App\Filament\Resources\Callbacks\Pages;

use App\Filament\Resources\Callbacks\CallbackResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCallback extends ViewRecord
{
    protected static string $resource = CallbackResource::class;

    public function getTitle(): string
    {
        return __('resources/callback.pages.view_title');
    }
}
