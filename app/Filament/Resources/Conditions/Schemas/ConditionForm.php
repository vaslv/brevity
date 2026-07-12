<?php

namespace App\Filament\Resources\Conditions\Schemas;

use App\Services\Links\Conditions\ConditionRegistry;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentTimezone;

class ConditionForm
{
    public static function configure(Schema $schema): Schema
    {
        $registry = app(ConditionRegistry::class);

        $typeOptions = collect($registry->types())
            ->mapWithKeys(fn (string $type) => [
                $type => __('resources/condition.types.'.$type),
            ])
            ->all();

        return $schema
            ->components([
                Select::make('type')
                    ->label(__('resources/condition.fields.type'))
                    ->options($typeOptions)
                    ->required()
                    ->live()
                    ->disabledOn('edit')
                    ->helperText(__('resources/condition.helpers.type_immutable')),
                Group::make()
                    ->schema(fn (callable $get): array => match ($get('type')) {
                        'time_before' => [
                            DateTimePicker::make('data.before')
                                ->label(__('resources/condition.data_fields.time_before.before'))
                                ->helperText(__('resources/condition.data_fields.time_before.before_help'))
                                ->seconds(true)
                                ->required()
                                ->dehydrateStateUsing(
                                    fn ($state) => $state
                                        ? CarbonImmutable::parse($state, FilamentTimezone::get())->format('Y-m-d\TH:i:sP')
                                        : null,
                                ),
                        ],
                        'after_date' => [
                            DateTimePicker::make('data.after')
                                ->label(__('resources/condition.data_fields.after_date.after'))
                                ->helperText(__('resources/condition.data_fields.after_date.after_help'))
                                ->seconds(true)
                                ->required()
                                ->dehydrateStateUsing(
                                    fn ($state) => $state
                                        ? CarbonImmutable::parse($state, FilamentTimezone::get())->format('Y-m-d\TH:i:sP')
                                        : null,
                                ),
                        ],
                        'query_param' => [
                            TextInput::make('data.key')
                                ->label(__('resources/condition.data_fields.query_param.key'))
                                ->helperText(__('resources/condition.data_fields.query_param.key_help'))
                                ->required()
                                ->maxLength(255),
                            TextInput::make('data.value')
                                ->label(__('resources/condition.data_fields.query_param.value'))
                                ->required()
                                ->maxLength(255),
                        ],
                        default => [],
                    }),
            ]);
    }
}
