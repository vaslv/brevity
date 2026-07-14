<?php

namespace App\Support;

/**
 * Parses the TRUSTED_PROXIES env value into the shape Laravel's
 * `trustProxies(at:)` expects (r37). Kept as a pure helper so the parsing is
 * unit-tested without booting the framework.
 */
final class TrustedProxies
{
    /**
     * Fallback when TRUSTED_PROXIES is unset — the prior hard-coded behavior.
     * Broad on purpose; production narrows it to the real LB subnet via env.
     *
     * @var array<int, string>
     */
    public const DEFAULT_RANGES = ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'];

    /**
     * @return array<int, string>|string A CIDR list, or '*' to trust any proxy.
     */
    public static function fromEnv(?string $raw): array|string
    {
        $raw = trim((string) $raw);

        return match (true) {
            $raw === '' => self::DEFAULT_RANGES,
            $raw === '*' => '*',
            default => array_values(array_filter(array_map('trim', explode(',', $raw)))),
        };
    }
}
