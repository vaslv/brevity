<?php

namespace App\Services\Links\Domains\Strategies;

use App\Models\Domain;
use App\Services\Links\Domains\DomainSelectionStrategy;
use Illuminate\Database\Eloquent\Builder;

/**
 * Picks a uniformly random domain from the pool.
 */
final class RandomDomainStrategy implements DomainSelectionStrategyHandler
{
    public function select(Builder $pool): ?Domain
    {
        return $pool->inRandomOrder()->first();
    }

    public static function strategy(): DomainSelectionStrategy
    {
        return DomainSelectionStrategy::Random;
    }
}
