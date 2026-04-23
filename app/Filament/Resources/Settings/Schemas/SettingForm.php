<?php

namespace App\Filament\Resources\Settings\Schemas;

use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\RichEditor;
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
                    ->label(__('resources/setting.fields.key'))
                    ->required()
                    ->unique()
                    ->columnSpan(2),
                TextInput::make('group')
                    ->label(__('resources/setting.fields.group'))
                    ->columnSpan(2),
                Select::make('type')
                    ->label(__('resources/setting.fields.type'))
                    ->options(array_combine(
                        array_column(SettingType::cases(), 'value'),
                        array_column(SettingType::cases(), 'value'),
                    ))
                    ->required()
                    ->reactive()
                    ->columnSpan(2),
                Group::make()
                    ->schema(fn (callable $get) => match ($get('type')) {
                        'boolean' => [
                            Toggle::make('value')
                                ->label(__('resources/setting.fields.value')),
                        ],
                        'integer' => [
                            TextInput::make('value')
                                ->label(__('resources/setting.fields.value'))
                                ->integer(),
                        ],
                        'float' => [
                            TextInput::make('value')
                                ->label(__('resources/setting.fields.value'))
                                ->numeric(),
                        ],
                        'json' => [
                            Textarea::make('value')
                                ->label(__('resources/setting.fields.value'))
                                ->rows(6),
                        ],
                        'markdown' => [
                            MarkdownEditor::make('value')
                                ->label(__('resources/setting.fields.value')),
                        ],
                        'html' => [
                            RichEditor::make('value')
                                ->label(__('resources/setting.fields.value')),
                        ],
                        default => [
                            TextInput::make('value')
                                ->label(__('resources/setting.fields.value')),
                        ],
                    })
                    ->columnSpan(2),
            ]);
    }
}
