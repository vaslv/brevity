<?php

namespace App\Filament\Resources\Domains\Schemas;

use App\Rules\ValidDomain;
use App\Services\Links\Domains\DomainName;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DomainForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('value')
                    ->label(__('resources/domain.fields.value'))
                    ->helperText(__('resources/domain.fields.value_hint'))
                    ->required()
                    ->rules(['bail', new ValidDomain])
                    ->mutateStateForValidationUsing(fn (?string $state): ?string => $state === null ? null : (DomainName::tryToAscii($state) ?? $state))
                    ->unique(ignoreRecord: true),
                Toggle::make('is_default')
                    ->label(__('resources/domain.fields.is_default'))
                    ->helperText(__('resources/domain.fields.is_default_hint')),
            ]);
    }
}
