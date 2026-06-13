<?php

namespace App\Filament\Resources\DomainGroups\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DomainGroupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label(__('resources/domain-group.fields.name')),
                TextEntry::make('domains.value')
                    ->label(__('resources/domain-group.fields.domains'))
                    ->badge()
                    ->placeholder(__('resources/domain-group.fields.domains_empty')),
                TextEntry::make('created_at')
                    ->label(__('resources/domain-group.fields.created_at'))
                    ->dateTime(),
            ]);
    }
}
