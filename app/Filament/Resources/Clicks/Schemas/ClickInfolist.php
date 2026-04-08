<?php

namespace App\Filament\Resources\Clicks\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ClickInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('service.name')
                    ->label('Service'),
                TextEntry::make('url.value')
                    ->label('Url'),
                TextEntry::make('link.title')
                    ->label('Link'),
                TextEntry::make('referrer.value')
                    ->label('Referrer'),
                TextEntry::make('userAgent.value')
                    ->label('User agent'),
                TextEntry::make('ipAddress.value')
                    ->label('Ip address'),
                TextEntry::make('created_at')
                    ->dateTime(),
            ]);
    }
}
