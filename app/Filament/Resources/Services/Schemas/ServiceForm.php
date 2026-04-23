<?php

namespace App\Filament\Resources\Services\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('resources/service.fields.name'))
                    ->required(),
                TextInput::make('callback_url')
                    ->label(__('resources/service.fields.callback_url'))
                    ->url(),
            ]);
    }
}
