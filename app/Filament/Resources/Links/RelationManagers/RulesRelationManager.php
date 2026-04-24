<?php

namespace App\Filament\Resources\Links\RelationManagers;

use App\Models\Condition;
use App\Services\Links\TransitionMode;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RulesRelationManager extends RelationManager
{
    protected static string $relationship = 'rules';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('url_id')
                    ->label(__('resources/link.rules.fields.url_id'))
                    ->relationship('url', 'value')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('condition_id')
                    ->label(__('resources/link.rules.fields.condition_id'))
                    ->options(
                        fn () => Condition::query()
                            ->orderBy('type')
                            ->get()
                            ->mapWithKeys(fn (Condition $c) => [
                                $c->id => sprintf('%s — %s', $c->type, json_encode($c->data, JSON_UNESCAPED_UNICODE)),
                            ])
                            ->all()
                    )
                    ->searchable()
                    ->nullable(),
                Select::make('transition_mode')
                    ->label(__('resources/link.rules.fields.transition_mode'))
                    ->helperText(__('resources/link.rules.fields.transition_mode_help'))
                    ->options(collect(TransitionMode::values())
                        ->mapWithKeys(fn (string $v) => [$v => __('resources/link.transition_modes.'.$v)])
                        ->all())
                    ->nullable(),
                TextInput::make('priority')
                    ->label(__('resources/link.rules.fields.priority'))
                    ->helperText(__('resources/link.rules.fields.priority_help'))
                    ->required()
                    ->numeric()
                    ->default(1),
            ]);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('resources/link.rules.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('priority')
            ->defaultSort('priority')
            ->columns([
                TextColumn::make('priority')
                    ->label(__('resources/link.rules.fields.priority'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('url.value')
                    ->label(__('resources/link.rules.fields.url'))
                    ->searchable()
                    ->limit(60),
                TextColumn::make('condition.type')
                    ->label(__('resources/link.rules.fields.condition'))
                    ->placeholder('-'),
                TextColumn::make('transition_mode')
                    ->label(__('resources/link.rules.fields.transition_mode'))
                    ->formatStateUsing(fn (?string $state) => $state ? __('resources/link.transition_modes.'.$state) : null)
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label(__('resources/link.rules.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
