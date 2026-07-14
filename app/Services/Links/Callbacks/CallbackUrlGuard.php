<?php

namespace App\Services\Links\Callbacks;

class CallbackUrlGuard
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    private const BLOCKED_HOSTNAMES = ['localhost', 'ip6-localhost', 'ip6-loopback'];

    public function isSafe(string $url): bool
    {
        $parsed = parse_url($url);

        if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
            return false;
        }

        if (! in_array(strtolower($parsed['scheme']), self::ALLOWED_SCHEMES, true)) {
            return false;
        }

        $host = strtolower($parsed['host']);

        if (in_array($host, self::BLOCKED_HOSTNAMES, true)) {
            return false;
        }

        $ips = $this->resolveIps($host);

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    /**
     * Resolve a hostname to IPv4 only. The callback HTTP client forces IPv4
     * (force_ip_resolve=v4 in SendCallbackJob), so validating AAAA here would
     * check addresses the client never connects to and open a cross-family
     * rebinding trick (guard sees a public A, client connects a private AAAA).
     * Keeping both to IPv4 aligns what is checked with what is dialed. A
     * literal IP is returned as-is (its family is validated by isPublicIp).
     *
     * @return array<int, string>
     */
    private function resolveIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A);

        if ($records === false || $records === []) {
            return [];
        }

        $ips = [];

        foreach ($records as $record) {
            if (! empty($record['ip'])) {
                $ips[] = $record['ip'];
            }
        }

        return $ips;
    }
}
