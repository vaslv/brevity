<?php

namespace App\Filament\Resources\Clicks\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ClickInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('service.name')
                    ->label(__('resources/click.fields.service')),
                TextEntry::make('url.value')
                    ->label(__('resources/click.fields.url')),
                TextEntry::make('link.code')
                    ->label(__('resources/click.fields.link'))
                    ->placeholder('-'),
                TextEntry::make('referrer.value')
                    ->label(__('resources/click.fields.referrer')),
                TextEntry::make('userAgent.value')
                    ->label(__('resources/click.fields.user_agent')),
                IconEntry::make('userAgent.is_bot')
                    ->label(__('resources/click.fields.is_bot'))
                    ->boolean()
                    ->default(false),
                TextEntry::make('ipAddress.value')
                    ->label(__('resources/click.fields.ip_address')),
                TextEntry::make('created_at')
                    ->label(__('resources/click.fields.created_at'))
                    ->dateTime(),
            ]);
    }
}
