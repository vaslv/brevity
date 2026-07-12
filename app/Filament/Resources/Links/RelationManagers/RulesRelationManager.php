<?php

namespace App\Filament\Resources\Links\RelationManagers;

use App\Models\Condition;
use App\Services\Links\TransitionMode;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

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
                    // No preload: the urls dictionary grows with every distinct
                    // destination, so search lazily instead of loading them all.
                    ->searchable()
                    ->required(),
                // Multi-condition rule (RUL-01): the rule matches when ALL
                // selected conditions match. Empty = unconditional.
                Select::make('conditions')
                    ->label(__('resources/link.rules.fields.conditions'))
                    ->relationship('conditions', 'type')
                    ->multiple()
                    ->getOptionLabelFromRecordUsing(fn (Condition $c): string => $c->describe())
                    ->searchable()
                    ->preload(),
                // A/B split (GAP-04): 2+ weighted targets. Empty = the rule
                // uses its own URL above.
                Repeater::make('variants')
                    ->label(__('resources/link.rules.fields.variants'))
                    ->helperText(__('resources/link.rules.fields.variants_help'))
                    ->relationship()
                    ->schema([
                        Select::make('url_id')
                            ->label(__('resources/link.rules.fields.variant_url'))
                            ->relationship('url', 'value')
                            ->searchable()
                            ->required(),
                        TextInput::make('weight')
                            ->label(__('resources/link.rules.fields.variant_weight'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1000)
                            ->default(1)
                            ->required(),
                        TextInput::make('label')
                            ->label(__('resources/link.rules.fields.variant_label'))
                            ->maxLength(64),
                    ])
                    ->columns(3)
                    ->minItems(0)
                    // No empty starter row: a rule without a split must submit
                    // an empty variant set, not one blank variant.
                    ->defaultItems(0)
                    // Either no split at all, or a real 2+ arm split.
                    ->rule(fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                        if (is_array($value) && count($value) === 1) {
                            $fail(__('resources/link.rules.fields.variants_min'));
                        }
                    }),
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
                    ->default(1)
                    // (link_id, priority) is unique in the DB; validate per-link
                    // so a duplicate shows a form error instead of a 500.
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, RelationManager $livewire): Unique => $rule
                            ->where('link_id', $livewire->getOwnerRecord()->getKey()),
                    ),
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
            // Eager-load conditions for the AND-set column below (no N+1).
            ->modifyQueryUsing(fn ($query) => $query->with('conditions'))
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
                TextColumn::make('conditions')
                    ->label(__('resources/link.rules.fields.conditions'))
                    ->state(fn ($record): ?string => $record->conditions->isNotEmpty()
                        ? $record->conditions->map(fn (Condition $c) => $c->describe())->join(' & ')
                        : null)
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
                CreateAction::make()
                    ->label(__('resources/link.rules.actions.create_label'))
                    ->modalHeading(__('resources/link.rules.actions.create_heading')),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading(__('resources/link.rules.actions.edit_heading')),
                DeleteAction::make()
                    ->modalHeading(__('resources/link.rules.actions.delete_heading')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
