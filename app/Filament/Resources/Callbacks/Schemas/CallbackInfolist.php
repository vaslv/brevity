<?php

namespace App\Filament\Resources\Callbacks\Schemas;

use App\Services\Links\Callbacks\CallbackStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CallbackInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('service.name')
                    ->label(__('resources/callback.fields.service')),
                TextEntry::make('click_id')
                    ->label(__('resources/callback.fields.click_id'))
                    ->numeric(),
                TextEntry::make('response_code')
                    ->label(__('resources/callback.fields.response_code'))
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('response_body')
                    ->label(__('resources/callback.fields.response_body'))
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('status')
                    ->label(__('resources/callback.fields.status'))
                    ->formatStateUsing(fn (CallbackStatus|string|null $state) => $state
                        ? __('resources/callback.statuses.'.($state instanceof CallbackStatus ? $state->value : $state))
                        : null),
                TextEntry::make('attempts')
                    ->label(__('resources/callback.fields.attempts'))
                    ->numeric(),
                TextEntry::make('last_attempt_at')
                    ->label(__('resources/callback.fields.last_attempt_at'))
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->label(__('resources/callback.fields.created_at'))
                    ->dateTime(),
            ]);
    }
}
