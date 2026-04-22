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

class RulesRelationManager extends RelationManager
{
    protected static string $relationship = 'rules';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('url_id')
                    ->relationship('url', 'value')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('condition_id')
                    ->label('Condition')
                    ->options(fn () => Condition::query()
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
                    ->options(collect(TransitionMode::values())
                        ->mapWithKeys(fn (string $v) => [$v => ucfirst($v)])
                        ->all())
                    ->nullable(),
                TextInput::make('priority')
                    ->required()
                    ->numeric()
                    ->default(1),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('priority')
            ->defaultSort('priority')
            ->columns([
                TextColumn::make('priority')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('url.value')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('condition.type')
                    ->placeholder('-'),
                TextColumn::make('transition_mode')
                    ->placeholder('-'),
                TextColumn::make('created_at')
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
