<?php

namespace App\Filament\Resources\Conditions;

use App\Filament\Resources\Conditions\Pages\CreateCondition;
use App\Filament\Resources\Conditions\Pages\EditCondition;
use App\Filament\Resources\Conditions\Pages\ListConditions;
use App\Filament\Resources\Conditions\Pages\ViewCondition;
use App\Filament\Resources\Conditions\Schemas\ConditionForm;
use App\Filament\Resources\Conditions\Schemas\ConditionInfolist;
use App\Filament\Resources\Conditions\Tables\ConditionsTable;
use App\Models\Condition;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConditionResource extends Resource
{
    protected static ?string $model = Condition::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ConditionForm::configure($schema);
    }

    public static function getModelLabel(): string
    {
        return __('resources/condition.label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.dictionaries');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources/condition.navigation_label');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConditions::route('/'),
            'create' => CreateCondition::route('/create'),
            'view' => ViewCondition::route('/{record}'),
            'edit' => EditCondition::route('/{record}/edit'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/condition.plural_label');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return ConditionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConditionsTable::configure($table);
    }
}
