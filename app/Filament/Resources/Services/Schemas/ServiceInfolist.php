<?php

namespace App\Filament\Resources\Services\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ServiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label(__('resources/service.fields.name')),
                TextEntry::make('callback_url')
                    ->label(__('resources/service.fields.callback_url'))
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->label(__('resources/service.fields.created_at'))
                    ->dateTime(),
            ]);
    }
}
