<?php

namespace App\Filament\Resources\Callbacks\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CallbackInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('service.name')
                    ->label('Service'),
                TextEntry::make('click_id')
                    ->numeric(),
                TextEntry::make('response_code')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('response_body')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('status'),
                TextEntry::make('attempts')
                    ->numeric(),
                TextEntry::make('last_attempt_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime(),
            ]);
    }
}
