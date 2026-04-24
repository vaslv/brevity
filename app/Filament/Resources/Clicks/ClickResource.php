<?php

namespace App\Filament\Resources\Clicks;

use App\Filament\Resources\Clicks\Pages\ListClicks;
use App\Filament\Resources\Clicks\Pages\ViewClick;
use App\Filament\Resources\Clicks\Schemas\ClickInfolist;
use App\Filament\Resources\Clicks\Tables\ClicksTable;
use App\Models\Click;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ClickResource extends Resource
{
    protected static ?string $model = Click::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCursorArrowRipple;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('resources/click.label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.analytics');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources/click.navigation_label');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClicks::route('/'),
            'view' => ViewClick::route('/{record}'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/click.plural_label');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return ClickInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClicksTable::configure($table);
    }
}
