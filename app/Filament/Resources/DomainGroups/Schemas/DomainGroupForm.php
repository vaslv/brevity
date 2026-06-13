<?php

namespace App\Filament\Resources\DomainGroups\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

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
                    ->unique(ignoreRecord: true)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                        // Convenience: seed code from the name, but never clobber
                        // an existing code — it is the stable API identifier.
                        if (blank($get('code'))) {
                            $set('code', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('code')
                    ->label(__('resources/domain-group.fields.code'))
                    ->helperText(__('resources/domain-group.fields.code_hint'))
                    ->required()
                    ->maxLength(255)
                    ->rule('alpha_dash')
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
