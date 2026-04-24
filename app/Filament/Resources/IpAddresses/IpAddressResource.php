<?php

namespace App\Filament\Resources\IpAddresses;

use App\Filament\Resources\IpAddresses\Pages\ListIpAddresses;
use App\Filament\Resources\IpAddresses\Pages\ViewIpAddress;
use App\Filament\Resources\IpAddresses\Schemas\IpAddressInfolist;
use App\Filament\Resources\IpAddresses\Tables\IpAddressesTable;
use App\Models\IpAddress;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class IpAddressResource extends Resource
{
    protected static ?string $model = IpAddress::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServer;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('resources/ip_address.label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.dictionaries');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources/ip_address.navigation_label');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIpAddresses::route('/'),
            'view' => ViewIpAddress::route('/{record}'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/ip_address.plural_label');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return IpAddressInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IpAddressesTable::configure($table);
    }
}
