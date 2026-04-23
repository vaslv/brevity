<?php

namespace App\Filament\Resources\Referrers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ReferrerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('value')
                    ->label(__('resources/referrer.fields.value')),
                TextEntry::make('created_at')
                    ->label(__('resources/referrer.fields.created_at'))
                    ->dateTime(),
            ]);
    }
}
