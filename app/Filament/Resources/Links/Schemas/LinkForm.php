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
                    ->label(__('resources/link.fields.service_id'))
                    ->relationship('service', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('domain_id')
                    ->label(__('resources/link.fields.domain_id'))
                    ->relationship('domain', 'value')
                    ->searchable()
                    ->preload(),
                TextInput::make('title')
                    ->label(__('resources/link.fields.title'))
                    ->columnSpan(2),
                Toggle::make('forward_query')
                    ->label(__('resources/link.fields.forward_query'))
                    ->required()
                    ->columnSpan(2),
                KeyValue::make('callback_data')
                    ->label(__('resources/link.fields.callback_data'))
                    ->reorderable()
                    ->keyLabel(__('resources/link.fields.callback_data_key'))
                    ->valueLabel(__('resources/link.fields.callback_data_value'))
                    ->addActionLabel(__('resources/link.fields.callback_data_add'))
                    ->nullable()
                    ->columnSpan(2),
            ]);
    }
}
