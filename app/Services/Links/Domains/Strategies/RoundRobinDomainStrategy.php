<?php

namespace App\Services\Links\Domains\Strategies;

use App\Models\Domain;
use App\Services\Links\Domains\DomainSelectionStrategy;
use Illuminate\Database\Eloquent\Builder;

/**
 * Even rotation: picks the least-recently-assigned domain — the one whose most
 * recent link is the oldest, with never-used domains first. Because each new
 * link stamps its domain as "used now", successive requests walk the pool in a
 * cycle.
 *
 * The order is derived from link history (not a stored cursor), so it stays
 * correct as domains are added/removed. Under simultaneous requests two links
 * may briefly land on the same domain (best-effort, not a hard guarantee); the
 * rotation self-corrects on the next request. Soft-deleted links are ignored
 * (the `links` relation applies the SoftDeletes scope).
 */
final class RoundRobinDomainStrategy implements DomainSelectionStrategyHandler
{
    public function select(Builder $pool): ?Domain
    {
        return $pool
            ->withMax('links', 'created_at')
            ->orderByRaw('links_max_created_at asc nulls first')
            ->orderBy('id')
            ->first();
    }

    public static function strategy(): DomainSelectionStrategy
    {
        return DomainSelectionStrategy::RoundRobin;
    }
}
