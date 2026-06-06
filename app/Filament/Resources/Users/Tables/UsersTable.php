<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        // No delete/bulk-delete: users are never deletable from the panel, so an
        // admin can't lock everyone out. Removal, if ever needed, is done via the
        // console (e.g. php artisan tinker).
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('resources/user.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('resources/user.fields.email'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                IconColumn::make('email_verified_at')
                    ->label(__('resources/user.fields.verified'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('resources/user.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
