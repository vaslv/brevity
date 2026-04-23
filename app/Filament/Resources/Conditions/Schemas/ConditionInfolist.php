<?php

namespace App\Filament\Resources\Conditions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ConditionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('type')
                    ->label(__('resources/condition.fields.type'))
                    ->formatStateUsing(fn (string $state): string => __('resources/condition.types.'.$state)),
                TextEntry::make('data')
                    ->label(__('resources/condition.fields.data'))
                    ->formatStateUsing(fn ($state): string => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->label(__('resources/condition.fields.created_at'))
                    ->dateTime(),
            ]);
    }
}
