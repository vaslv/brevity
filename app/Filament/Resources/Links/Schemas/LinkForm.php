<?php

namespace App\Filament\Resources\Links\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LinkForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('service_id')
                    ->relationship('service', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('domain_id')
                    ->relationship('domain', 'value')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('code'),
                TextInput::make('title'),
                Toggle::make('forward_query')
                    ->required(),
                KeyValue::make('callback_data')
                    ->reorderable()
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->addActionLabel('Add')
                    ->nullable(),
            ]);
    }
}
