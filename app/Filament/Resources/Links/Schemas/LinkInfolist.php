<?php

namespace App\Filament\Resources\Links\Schemas;

use App\Models\Link;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class LinkInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('service.name')
                    ->label('Service'),
                TextEntry::make('domain.id')
                    ->label('Domain'),
                TextEntry::make('code')
                    ->placeholder('-'),
                TextEntry::make('title')
                    ->placeholder('-'),
                IconEntry::make('forward_query')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Link $record): bool => $record->trashed()),
            ]);
    }
}
