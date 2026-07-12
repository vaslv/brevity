<?php

namespace App\Filament\Resources\Conditions\Schemas;

use App\Services\Links\Conditions\ConditionRegistry;
use App\Services\Links\Conditions\DeviceConditionHandler;
use App\Services\Links\Conditions\IpAddressConditionHandler;
use Carbon\CarbonImmutable;
use Closure;
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
                        'device' => [
                            Select::make('data.device')
                                ->label(__('resources/condition.data_fields.device.device'))
                                ->helperText(__('resources/condition.data_fields.device.device_help'))
                                ->options(collect(DeviceConditionHandler::TYPES)
                                    ->mapWithKeys(fn (string $t) => [$t => __('resources/condition.device_types.'.$t)])
                                    ->all())
                                ->required(),
                        ],
                        'language' => [
                            TextInput::make('data.language')
                                ->label(__('resources/condition.data_fields.language.language'))
                                ->helperText(__('resources/condition.data_fields.language.language_help'))
                                ->required()
                                ->rule('regex:/^[a-zA-Z]{2,3}$/'),
                            TextInput::make('data.country')
                                ->label(__('resources/condition.data_fields.language.country'))
                                ->helperText(__('resources/condition.data_fields.language.country_help'))
                                ->rule('regex:/^[a-zA-Z]{2}$/'),
                        ],
                        'ip_address' => [
                            TextInput::make('data.ip')
                                ->label(__('resources/condition.data_fields.ip_address.ip'))
                                ->helperText(__('resources/condition.data_fields.ip_address.ip_help'))
                                ->required()
                                ->maxLength(64)
                                // Same pattern check as the API path: the admin
                                // direct-create bypasses the request validator.
                                ->rule(fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                                    if (! is_string($value) || ! IpAddressConditionHandler::isValidPattern($value)) {
                                        $fail(__('resources/condition.data_fields.ip_address.invalid'));
                                    }
                                }),
                        ],
                        default => [],
                    }),
            ]);
    }
}
