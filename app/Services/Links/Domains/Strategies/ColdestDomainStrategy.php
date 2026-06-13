<?php

namespace App\Services\Links\Domains\Strategies;

use App\Models\Domain;
use App\Services\Links\Domains\DomainSelectionStrategy;
use Illuminate\Database\Eloquent\Builder;

/**
 * Least-used: picks the domain with the fewest links created within the rolling
 * window (config `domains.coldest_period_days`). Domains unused in the window
 * come first; ties break by id for a deterministic choice. Soft-deleted links
 * are ignored (the `links` relation applies the SoftDeletes scope).
 */
final class ColdestDomainStrategy implements DomainSelectionStrategyHandler
{
    public function select(Builder $pool): ?Domain
    {
        $since = now()->subDays($this->periodDays());

        return $pool
            ->withCount(['links as recent_links_count' => function (Builder $query) use ($since): void {
                $query->where('created_at', '>=', $since);
            }])
            ->orderBy('recent_links_count')
            ->orderBy('id')
            ->first();
    }

    public static function strategy(): DomainSelectionStrategy
    {
        return DomainSelectionStrategy::Coldest;
    }

    private function periodDays(): int
    {
        return max(1, (int) config('domains.coldest_period_days', 30));
    }
}
