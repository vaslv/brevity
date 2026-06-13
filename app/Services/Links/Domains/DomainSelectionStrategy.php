<?php

namespace App\Services\Links\Domains;

/**
 * How a domain is picked for a link when the request does not name one
 * explicitly. Selection runs over a pool of domains (a group, or all domains).
 */
enum DomainSelectionStrategy: string
{
    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $strategy): string => $strategy->value, self::cases());
    }
    case Coldest = 'coldest';
    case Random = 'random';
    case RoundRobin = 'round_robin';
}
