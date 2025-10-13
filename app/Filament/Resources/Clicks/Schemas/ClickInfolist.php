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
                TextEntry::make('link.title')
                    ->label('Link'),
                TextEntry::make('url.id')
                    ->label('Url'),
                TextEntry::make('referrer.id')
                    ->label('Referrer'),
                TextEntry::make('userAgent.id')
                    ->label('User agent'),
                TextEntry::make('ipAddress.id')
                    ->label('Ip address'),
                TextEntry::make('created_at')
                    ->dateTime(),
            ]);
    }
}
