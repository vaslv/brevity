<?php

namespace App\Filament\Resources\Conditions\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class ConditionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->options([
                        'country' => 'Country',
                        'language' => 'Language',
                        'device' => 'Device',
                        'os' => 'OS',
                        'browser' => 'Browser',
                    ])
                    ->searchable()
                    ->required(),
                KeyValue::make('value')
                    ->reorderable()
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->addActionLabel('Add')
                    ->nullable(),
            ]);
    }
}
