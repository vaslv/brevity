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
use UnitEnum;

class IpAddressResource extends Resource
{
    protected static ?string $model = IpAddress::class;

    protected static ?string $modelLabel = 'IP Address';

    protected static string|null|UnitEnum $navigationGroup = 'Dictionaries';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServer;

    protected static ?string $navigationLabel = 'IP Addresses';

    protected static ?int $navigationSort = 3;

    protected static ?string $pluralModelLabel = 'IP Addresses';

    public static function getPages(): array
    {
        return [
            'index' => ListIpAddresses::route('/'),
            'view' => ViewIpAddress::route('/{record}'),
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
        return IpAddressInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IpAddressesTable::configure($table);
    }
}
