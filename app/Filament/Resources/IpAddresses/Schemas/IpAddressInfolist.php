<?php

namespace App\Filament\Resources\IpAddresses\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class IpAddressInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('value')
                    ->label(__('resources/ip_address.fields.value')),
                TextEntry::make('created_at')
                    ->label(__('resources/ip_address.fields.created_at'))
                    ->dateTime(),
            ]);
    }
}
