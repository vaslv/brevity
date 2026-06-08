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
     * IMPORTANT: this strategy relies on a UNIQUE index for `value`.
     *
     * Dictionary values (referrers, user agents, IPs) recur heavily across
     * clicks, so the row almost always already exists. A SELECT-first lookup
     * resolves that hot path in a single query; only a genuinely new value falls
     * through to an INSERT. `ON CONFLICT (value) DO NOTHING RETURNING id` keeps
     * that insert race-safe and hands back the id with no extra round-trip when
     * our INSERT wins; a final re-SELECT covers the lost-race case.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function resolveId(string $modelClass, ?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $table = (new $modelClass)->getTable();

        $id = $this->selectId($table, $value);

        if ($id !== null) {
            return $id;
        }

        $wrappedTable = DB::getQueryGrammar()->wrapTable($table);

        $id = DB::scalar(
            "insert into {$wrappedTable} (\"value\") values (?) on conflict (\"value\") do nothing returning \"id\"",
            [$value],
            useReadPdo: false,
        );

        if ($id !== null) {
            return (int) $id;
        }

        // Lost the insert race: a concurrent writer committed the same value
        // between our SELECT and INSERT, so DO NOTHING returned no row.
        $id = $this->selectId($table, $value);

        if ($id === null) {
            throw new RuntimeException(sprintf(
                'Unable to resolve dictionary ID for table "%s".',
                $table
            ));
        }

        return $id;
    }

    private function selectId(string $table, string $value): ?int
    {
        $id = DB::table($table)
            ->where('value', $value)
            ->value('id');

        return $id === null ? null : (int) $id;
    }
}
