<?php

namespace App\Services\Links\Domains;

use App\Models\Domain;
use Illuminate\Database\Eloquent\Builder;

class DomainSelector
{
    public function __construct(private readonly DomainStrategyRegistry $registry) {}

    /**
     * Pick a domain by strategy. Scoped to a group when $groupId is given,
     * otherwise across all domains. Returns null when the scope has no domains.
     */
    public function select(DomainSelectionStrategy $strategy, ?int $groupId = null): ?Domain
    {
        $pool = Domain::query();

        if ($groupId !== null) {
            $pool->whereHas('domainGroups', fn (Builder $groups) => $groups->whereKey($groupId));
        }

        return $this->registry->handlerFor($strategy)->select($pool);
    }
}
