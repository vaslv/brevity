<?php

namespace App\Filament\Resources\Callbacks;

use App\Filament\Resources\Callbacks\Pages\ListCallbacks;
use App\Filament\Resources\Callbacks\Pages\ViewCallback;
use App\Filament\Resources\Callbacks\Schemas\CallbackInfolist;
use App\Filament\Resources\Callbacks\Tables\CallbacksTable;
use App\Models\Callback;
use App\Services\Links\Callbacks\CallbackStatus;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CallbackResource extends Resource
{
    protected static ?string $model = Callback::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('resources/callback.label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.analytics');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources/callback.navigation_label');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Callback::query()->where('status', CallbackStatus::Failed)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('resources/callback.navigation_badge_tooltip');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCallbacks::route('/'),
            'view' => ViewCallback::route('/{record}'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/callback.plural_label');
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
