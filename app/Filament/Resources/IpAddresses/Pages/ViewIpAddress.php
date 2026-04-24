<?php

namespace App\Filament\Resources\IpAddresses\Pages;

use App\Filament\Resources\IpAddresses\IpAddressResource;
use Filament\Resources\Pages\ViewRecord;

class ViewIpAddress extends ViewRecord
{
    protected static string $resource = IpAddressResource::class;

    public function getTitle(): string
    {
        return __('resources/ip_address.pages.view_title');
    }
}
