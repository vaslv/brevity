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
                    ->label(__('resources/link.fields.service')),
                TextEntry::make('url')
                    ->label(__('resources/link.fields.short_url'))
                    ->copyable()
                    ->copyMessage(__('resources/link.fields.short_url_copied'))
                    ->url(fn (Link $record): string => $record->url, shouldOpenInNewTab: true)
                    ->placeholder('-'),
                TextEntry::make('domain.value')
                    ->label(__('resources/link.fields.domain'))
                    ->placeholder('-'),
                TextEntry::make('code')
                    ->label(__('resources/link.fields.code'))
                    ->placeholder('-'),
                TextEntry::make('title')
                    ->label(__('resources/link.fields.title'))
                    ->placeholder('-'),
                IconEntry::make('forward_query')
                    ->label(__('resources/link.fields.forward_query'))
                    ->boolean(),
                TextEntry::make('created_at')
                    ->label(__('resources/link.fields.created_at'))
                    ->dateTime(),
                TextEntry::make('deleted_at')
                    ->label(__('resources/link.fields.deleted_at'))
                    ->dateTime()
                    ->visible(fn (Link $record): bool => $record->trashed()),
            ]);
    }
}
