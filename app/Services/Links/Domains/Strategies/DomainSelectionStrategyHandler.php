<?php

namespace App\Services\Links\Domains\Strategies;

use App\Models\Domain;
use App\Services\Links\Domains\DomainSelectionStrategy;
use Illuminate\Database\Eloquent\Builder;

interface DomainSelectionStrategyHandler
{
    /**
     * Pick one domain out of the in-scope pool, or null when the pool is empty.
     *
     * @param  Builder<Domain>  $pool  domains already scoped to a group (or all domains)
     */
    public function select(Builder $pool): ?Domain;

    public static function strategy(): DomainSelectionStrategy;
}
