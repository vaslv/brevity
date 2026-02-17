<?php

namespace App\Filament\Resources\Settings\Schemas;

use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Vaslv\LaravelSettings\SettingType;

class SettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->required()
                    ->unique()
                    ->columnSpan(2),
                TextInput::make('group')
                    ->columnSpan(2),
                Select::make('type')
                    ->options(array_combine(
                        array_column(SettingType::cases(), 'value'),
                        array_column(SettingType::cases(), 'value'),
                    ))
                    ->required()
                    ->reactive()
                    ->columnSpan(2),
                Group::make()
                    ->schema(fn(callable $get) => match ($get('type')) {
                        'boolean' => [
                            Toggle::make('value'),
                        ],
                        'integer' => [
                            TextInput::make('value')
                                ->integer(),
                        ],
                        'float' => [
                            TextInput::make('value')
                                ->numeric(),
                        ],
                        'json' => [
                            Textarea::make('value')
                                ->rows(6),
                        ],
                        'markdown' => [
                            MarkdownEditor::make('value'),
                        ],
                        default => [
                            TextInput::make('value'),
                        ],
                    })
                    ->columnSpan(2),
            ]);
    }
}
