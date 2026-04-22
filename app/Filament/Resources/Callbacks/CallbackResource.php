<?php

namespace App\Filament\Resources\Callbacks;

use App\Filament\Resources\Callbacks\Pages\ListCallbacks;
use App\Filament\Resources\Callbacks\Pages\ViewCallback;
use App\Filament\Resources\Callbacks\Schemas\CallbackInfolist;
use App\Filament\Resources\Callbacks\Tables\CallbacksTable;
use App\Models\Callback;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CallbackResource extends Resource
{
    protected static ?string $model = Callback::class;

    protected static string|null|UnitEnum $navigationGroup = 'Analytics';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?int $navigationSort = 2;

    public static function getPages(): array
    {
        return [
            'index' => ListCallbacks::route('/'),
            'view' => ViewCallback::route('/{record}'),
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
        return CallbackInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CallbacksTable::configure($table);
    }
}
