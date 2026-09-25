<?php

namespace App\Support;

use App\Services\Links\Domains\DomainName;
use InvalidArgumentException;

final class HttpHost
{
    public static function normalize(string $host): string
    {
        return self::tryNormalize($host)
            ?? throw new InvalidArgumentException('Invalid HTTP host.');
    }

    public static function tryNormalize(string $host): ?string
    {
        $host = trim($host, " \t\r\n");

        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $address = substr($host, 1, -1);

            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
                return null;
            }

            return '['.inet_ntop(inet_pton($address)).']';
        }

        return DomainName::tryToAscii($host);
    }
}
