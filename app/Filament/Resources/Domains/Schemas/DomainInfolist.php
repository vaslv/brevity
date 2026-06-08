<?php

namespace App\Filament\Resources\Domains\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DomainInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('value')
                    ->label(__('resources/domain.fields.value')),
                IconEntry::make('is_default')
                    ->label(__('resources/domain.fields.is_default'))
                    ->boolean(),
                TextEntry::make('created_at')
                    ->label(__('resources/domain.fields.created_at'))
                    ->dateTime(),
            ]);
    }
}
