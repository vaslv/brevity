<?php

namespace App\Filament\Resources\DomainGroups\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DomainGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('resources/domain-group.fields.name'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('domains')
                    ->label(__('resources/domain-group.fields.domains'))
                    ->helperText(__('resources/domain-group.fields.domains_hint'))
                    // Many-to-many: a domain can belong to several groups, so the
                    // same domain may be picked here and in any other group.
                    ->relationship('domains', 'value')
                    ->multiple()
                    ->preload()
                    ->searchable(),
            ]);
    }
}
