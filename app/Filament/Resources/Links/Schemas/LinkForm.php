<?php

namespace App\Filament\Resources\Links\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
                    ->helperText(__('resources/link.fields.forward_query_help'))
                    ->required()
                    ->columnSpan(2),
                // JSON editor (not KeyValue) so nested callback templates — which
                // the API supports, e.g. {"meta":{"referrer":"..."}} — round-trip
                // without being flattened/corrupted.
                Textarea::make('callback_data')
                    ->label(__('resources/link.fields.callback_data'))
                    ->helperText(__('resources/link.fields.callback_data_help'))
                    ->rows(8)
                    ->rules(['nullable', 'json'])
                    ->formatStateUsing(fn ($state): ?string => filled($state)
                        ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        : null)
                    ->dehydrateStateUsing(fn (?string $state) => filled($state) ? json_decode($state, true) : null)
                    ->nullable()
                    ->columnSpan(2),
            ]);
    }
}
