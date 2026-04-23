<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label(__('resources/user.fields.name')),
                TextEntry::make('email')
                    ->label(__('resources/user.fields.email'))
                    ->copyable(),
                TextEntry::make('email_verified_at')
                    ->label(__('resources/user.fields.email_verified_at'))
                    ->dateTime()
                    ->placeholder(__('resources/user.placeholders.not_verified')),
                TextEntry::make('created_at')
                    ->label(__('resources/user.fields.created_at'))
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->label(__('resources/user.fields.updated_at'))
                    ->dateTime(),
            ]);
    }
}
