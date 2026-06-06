<?php

namespace App\Filament\Support;

use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * A DeleteBulkAction that turns a foreign-key restriction (Postgres SQLSTATE
 * 23503) into a friendly notification instead of an unhandled 500. Used on the
 * dictionary/Service tables whose rows are referenced by restrictOnDelete FKs
 * (e.g. a Url still referenced by clicks/rules cannot be deleted). The deletes
 * run in a transaction so a single restricted row leaves the whole batch intact.
 */
class RestrictedDeleteBulkAction
{
    private const FOREIGN_KEY_VIOLATION = '23503';

    public static function make(): DeleteBulkAction
    {
        return DeleteBulkAction::make()
            ->action(function (Collection $records): void {
                try {
                    DB::transaction(
                        static fn () => $records->each(static fn (Model $record) => $record->delete())
                    );
                } catch (QueryException $e) {
                    if ((string) $e->getCode() !== self::FOREIGN_KEY_VIOLATION) {
                        throw $e;
                    }

                    Notification::make()
                        ->danger()
                        ->title(__('filament/actions.restricted_delete.title'))
                        ->body(__('filament/actions.restricted_delete.body'))
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('filament/actions.deleted.title'))
                    ->send();
            });
    }
}
