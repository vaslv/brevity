<?php

namespace App\Filament\Resources\Urls\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UrlForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('value')
                    ->label(__('resources/url.fields.value'))
                    ->required(),
            ]);
    }
}
