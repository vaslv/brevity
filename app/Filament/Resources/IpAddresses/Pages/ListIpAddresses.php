<?php

namespace App\Filament\Resources\IpAddresses\Pages;

use App\Filament\Resources\IpAddresses\IpAddressResource;
use Filament\Resources\Pages\ListRecords;

class ListIpAddresses extends ListRecords
{
    protected static string $resource = IpAddressResource::class;
}
