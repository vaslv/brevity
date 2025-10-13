<?php

namespace App\Filament\Resources\Urls\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UrlInfolist
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
