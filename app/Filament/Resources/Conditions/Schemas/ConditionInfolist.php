<?php

namespace App\Filament\Resources\Conditions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ConditionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('type'),
                TextEntry::make('created_at')
                    ->dateTime(),
            ]);
    }
}
