<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('resources/user.fields.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('resources/user.fields.email'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                DateTimePicker::make('email_verified_at')
                    ->label(__('resources/user.fields.email_verified_at'))
                    ->nullable()
                    ->helperText(__('resources/user.helpers.email_verified_at')),
                TextInput::make('password')
                    ->label(__('resources/user.fields.password'))
                    ->password()
                    ->revealable()
                    ->rule(Password::default())
                    ->same('password_confirmation')
                    ->required(fn ($livewire): bool => $livewire instanceof CreateRecord)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn ($livewire): string => $livewire instanceof CreateRecord
                        ? ''
                        : __('resources/user.helpers.password_on_edit')),
                TextInput::make('password_confirmation')
                    ->label(__('resources/user.fields.password_confirmation'))
                    ->password()
                    ->revealable()
                    ->required(fn (Get $get): bool => filled($get('password')))
                    ->dehydrated(false),
            ]);
    }
}
