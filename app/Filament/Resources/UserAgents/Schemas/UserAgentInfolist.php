<?php

namespace App\Filament\Resources\UserAgents\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UserAgentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('value')
                    ->label(__('resources/user_agent.fields.value'))
                    ->columnSpanFull(),
                IconEntry::make('is_bot')
                    ->label(__('resources/user_agent.fields.is_bot'))
                    ->boolean(),
                TextEntry::make('created_at')
                    ->label(__('resources/user_agent.fields.created_at'))
                    ->dateTime(),
            ]);
    }
}
