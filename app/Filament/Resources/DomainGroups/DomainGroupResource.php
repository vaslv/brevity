<?php

namespace App\Filament\Resources\DomainGroups;

use App\Filament\Resources\DomainGroups\Pages\CreateDomainGroup;
use App\Filament\Resources\DomainGroups\Pages\EditDomainGroup;
use App\Filament\Resources\DomainGroups\Pages\ListDomainGroups;
use App\Filament\Resources\DomainGroups\Pages\ViewDomainGroup;
use App\Filament\Resources\DomainGroups\Schemas\DomainGroupForm;
use App\Filament\Resources\DomainGroups\Schemas\DomainGroupInfolist;
use App\Filament\Resources\DomainGroups\Tables\DomainGroupsTable;
use App\Models\DomainGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DomainGroupResource extends Resource
{
    protected static ?string $model = DomainGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return DomainGroupForm::configure($schema);
    }

    public static function getModelLabel(): string
    {
        return __('resources/domain-group.label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.main');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources/domain-group.navigation_label');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDomainGroups::route('/'),
            'create' => CreateDomainGroup::route('/create'),
            'view' => ViewDomainGroup::route('/{record}'),
            'edit' => EditDomainGroup::route('/{record}/edit'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/domain-group.plural_label');
    }

    public static function infolist(Schema $schema): Schema
    {
        return DomainGroupInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DomainGroupsTable::configure($table);
    }
}
