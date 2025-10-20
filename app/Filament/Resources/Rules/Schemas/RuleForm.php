<?php

namespace App\Filament\Resources\Rules\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('link_id')
                    ->relationship('link', 'code')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('url_id')
                    ->relationship('url', 'value')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('condition_id')
                    ->relationship('condition', 'type')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('priority')
                    ->numeric()
                    ->required(),
            ]);
    }
}
