<?php

namespace App\Services\Links\Clicks;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DictionaryValueResolver
{
    /**
     * Resolve dictionary row ID by value in a race-condition-safe way.
     *
     * IMPORTANT: this strategy relies on a unique index for `value`.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function resolveId(string $modelClass, ?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $model = new $modelClass;
        $table = $model->getTable();

        DB::table($table)->insertOrIgnore([
            'value' => $value,
        ]);

        $id = DB::table($table)
            ->where('value', $value)
            ->value('id');

        if ($id === null) {
            throw new RuntimeException(sprintf(
                'Unable to resolve dictionary ID for table "%s".',
                $table
            ));
        }

        return (int) $id;
    }
}
