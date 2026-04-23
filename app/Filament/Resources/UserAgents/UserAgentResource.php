<?php

namespace App\Filament\Resources\UserAgents;

use App\Filament\Resources\UserAgents\Pages\ListUserAgents;
use App\Filament\Resources\UserAgents\Pages\ViewUserAgent;
use App\Filament\Resources\UserAgents\Schemas\UserAgentInfolist;
use App\Filament\Resources\UserAgents\Tables\UserAgentsTable;
use App\Models\UserAgent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class UserAgentResource extends Resource
{
    protected static ?string $model = UserAgent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDeviceTablet;

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.dictionaries');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources/user_agent.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('resources/user_agent.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/user_agent.plural_label');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserAgents::route('/'),
            'view' => ViewUserAgent::route('/{record}'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserAgentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserAgentsTable::configure($table);
    }
}
