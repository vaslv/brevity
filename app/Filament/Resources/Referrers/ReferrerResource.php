<?php

namespace App\Filament\Resources\Referrers;

use App\Filament\Resources\Referrers\Pages\ListReferrers;
use App\Filament\Resources\Referrers\Pages\ViewReferrer;
use App\Filament\Resources\Referrers\Schemas\ReferrerInfolist;
use App\Filament\Resources\Referrers\Tables\ReferrersTable;
use App\Models\Referrer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ReferrerResource extends Resource
{
    protected static ?string $model = Referrer::class;

    protected static string|null|UnitEnum $navigationGroup = 'Dictionaries';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static ?int $navigationSort = 4;

    public static function getPages(): array
    {
        return [
            'index' => ListReferrers::route('/'),
            'view' => ViewReferrer::route('/{record}'),
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
        return ReferrerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReferrersTable::configure($table);
    }
}
