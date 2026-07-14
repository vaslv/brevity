<?php

namespace App\Filament\Support;

use Filament\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The single-record counterpart to RestrictedDeleteBulkAction: it turns a
 * foreign-key restriction (Postgres SQLSTATE 23503) into a friendly failure
 * notification instead of an unhandled 500. Use it on Edit-page headers for
 * records referenced by restrictOnDelete FKs (e.g. a Service still referenced
 * by links/clicks). Returning false from the delete callback routes through the
 * action's own failure() path, so the default success redirect/notification are
 * preserved and a restricted delete leaves the record intact on the same page.
 */
class RestrictedDeleteAction
{
    private const FOREIGN_KEY_VIOLATION = '23503';

    public static function make(): DeleteAction
    {
        return DeleteAction::make()
            ->failureNotificationTitle(__('filament/actions.restricted_delete.title'))
            ->failureNotificationBody(__('filament/actions.restricted_delete.body'))
            ->using(function (Model $record): bool {
                try {
                    DB::transaction(static fn () => $record->delete());
                } catch (QueryException $e) {
                    if ((string) $e->getCode() !== self::FOREIGN_KEY_VIOLATION) {
                        throw $e;
                    }

                    return false;
                }

                return true;
            });
    }
}
