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
                TextEntry::make('display_domain')
                    ->label(__('resources/domain.fields.value'))
                    ->copyable(),
                TextEntry::make('value')
                    ->label(__('resources/domain.fields.ascii'))
                    ->copyable(),
                TextEntry::make('url')
                    ->label(__('resources/domain.fields.url'))
                    ->copyable(),
                IconEntry::make('is_default')
                    ->label(__('resources/domain.fields.is_default'))
                    ->boolean(),
                TextEntry::make('created_at')
                    ->label(__('resources/domain.fields.created_at'))
                    ->dateTime(),
            ]);
    }
}
