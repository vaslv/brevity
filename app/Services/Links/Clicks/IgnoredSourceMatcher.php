<?php

namespace App\Services\Links\Clicks;

use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Traffic hygiene (docs/07-plans.md §4): visits from configured sources
 * (office IPs, monitoring) are never recorded — no click, no callback.
 * Supports exact IPs and CIDR ranges, IPv4 and IPv6.
 */
readonly class IgnoredSourceMatcher
{
    public function isIgnored(?string $ip): bool
    {
        if ($ip === null || $ip === '') {
            return false;
        }

        $sources = array_values(array_filter(
            array_map('trim', explode(',', (string) config('tracking.ignored_sources'))),
            fn (string $source): bool => $source !== '' && $this->isValidSource($source),
        ));

        if ($sources === []) {
            return false;
        }

        return IpUtils::checkIp($ip, $sources);
    }

    /**
     * A malformed entry must be skipped, not passed through: Symfony coerces a
     * garbage netmask ("10.0.0.0/abc") to /0, which would silently ignore ALL
     * traffic. Skipping an invalid entry only leaks a few office clicks into
     * stats — the safe failure direction.
     */
    private function isValidSource(string $source): bool
    {
        [$ip, $mask] = array_pad(explode('/', $source, 2), 2, null);

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if ($mask === null) {
            return true;
        }

        $maxMask = str_contains((string) $ip, ':') ? 128 : 32;

        return ctype_digit($mask) && (int) $mask <= $maxMask;
    }
}
