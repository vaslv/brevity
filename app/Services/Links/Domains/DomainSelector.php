<?php

namespace App\Services\Links\Domains;

use App\Models\Domain;
use Illuminate\Database\Eloquent\Builder;

class DomainSelector
{
    public function __construct(private readonly DomainStrategyRegistry $registry) {}

    /**
     * Pick a domain by strategy. Scoped to a group when $groupCode is given,
     * otherwise across all domains. Returns null when the scope has no domains.
     */
    public function select(DomainSelectionStrategy $strategy, ?string $groupCode = null): ?Domain
    {
        $pool = Domain::query();

        if ($groupCode !== null) {
            $pool->whereHas('domainGroups', fn (Builder $groups) => $groups->where('code', $groupCode));
        }

        return $this->registry->handlerFor($strategy)->select($pool);
    }
}
