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
                TextEntry::make('value'),
                TextEntry::make('created_at')
                    ->dateTime(),
            ]);
    }
}
