<?php

namespace App\Filament\Resources\LinkRules;

use App\Filament\Resources\LinkRules\Pages\CreateLinkRule;
use App\Filament\Resources\LinkRules\Pages\EditLinkRule;
use App\Filament\Resources\LinkRules\Pages\ListLinkRules;
use App\Filament\Resources\LinkRules\Pages\ViewLinkRule;
use App\Filament\Resources\LinkRules\Schemas\LinkRuleForm;
use App\Filament\Resources\LinkRules\Schemas\LinkRuleInfolist;
use App\Filament\Resources\LinkRules\Tables\LinkRulesTable;
use App\Models\LinkRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LinkRuleResource extends Resource
{
    protected static ?string $model = LinkRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return LinkRuleForm::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLinkRules::route('/'),
            'create' => CreateLinkRule::route('/create'),
            'view' => ViewLinkRule::route('/{record}'),
            'edit' => EditLinkRule::route('/{record}/edit'),
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
        return LinkRuleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LinkRulesTable::configure($table);
    }
}
