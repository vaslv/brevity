<?php

namespace App\Filament\Resources\Domains\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DomainForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('value')
                    ->label(__('resources/domain.fields.value'))
                    ->required()
                    ->unique(ignoreRecord: true),
            ]);
    }
}
