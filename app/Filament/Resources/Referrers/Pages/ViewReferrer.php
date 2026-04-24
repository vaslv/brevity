<?php

namespace App\Filament\Resources\Referrers\Pages;

use App\Filament\Resources\Referrers\ReferrerResource;
use Filament\Resources\Pages\ViewRecord;

class ViewReferrer extends ViewRecord
{
    protected static string $resource = ReferrerResource::class;

    public function getTitle(): string
    {
        return __('resources/referrer.pages.view_title');
    }
}
