<?php

namespace App\Filament\Resources\Conditions\Schemas;

use App\Services\Links\Conditions\ConditionRegistry;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;

class ConditionForm
{
    public static function configure(Schema $schema): Schema
    {
        $registry = app(ConditionRegistry::class);

        $typeOptions = collect($registry->types())
            ->mapWithKeys(fn (string $type) => [
                $type => str($type)->replace('_', ' ')->title()->toString(),
            ])
            ->all();

        return $schema
            ->components([
                Select::make('type')
                    ->options($typeOptions)
                    ->required()
                    ->live()
                    ->disabledOn('edit')
                    ->helperText('Type cannot be changed after creation.'),
                Group::make()
                    ->schema(fn (callable $get): array => match ($get('type')) {
                        'time_before' => [
                            DateTimePicker::make('data.before')
                                ->label('Match while current time is before')
                                ->seconds(true)
                                ->required()
                                ->timezone('UTC')
                                ->formatStateUsing(
                                    fn ($state) => $state
                                        ? CarbonImmutable::parse($state)->utc()->format('Y-m-d H:i:s')
                                        : null,
                                )
                                ->dehydrateStateUsing(
                                    fn ($state) => $state
                                        ? CarbonImmutable::parse($state, 'UTC')->format('Y-m-d\TH:i:sP')
                                        : null,
                                ),
                        ],
                        default => [],
                    }),
            ]);
    }
}
