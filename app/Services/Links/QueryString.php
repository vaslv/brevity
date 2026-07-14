<?php

namespace App\Services\Links;

/**
 * Query-string helpers that keep literal parameter names intact. PHP's parse_str
 * (and the request query bag it feeds) rewrites `.`, space and `[` in a param
 * name to `_`, so a partner's `sub.id` silently becomes `sub_id`. These hand-
 * split on `&`/`=` instead, mirroring CallbackDataRenderer's own reasoning, so a
 * dotted param survives both condition matching and forward_query (r44/r45).
 */
final class QueryString
{
    /**
     * Rebuild a key => value map into a query string, encoding each pair without
     * re-mangling literal key names (rawurlencode leaves `.`, `-`, `_`, `~`).
     *
     * @param  array<string, string>  $pairs
     */
    public static function build(array $pairs): string
    {
        $parts = [];

        foreach ($pairs as $key => $value) {
            $parts[] = rawurlencode((string) $key).'='.rawurlencode($value);
        }

        return implode('&', $parts);
    }

    /**
     * Parse a query string into a literal key => value map. A repeated key keeps
     * the last value (as parse_str did); a bare `?flag` yields an empty value.
     *
     * @return array<string, string>
     */
    public static function parse(?string $query): array
    {
        if ($query === null || $query === '') {
            return [];
        }

        $pairs = [];

        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $key = urldecode($key);

            if ($key === '') {
                continue;
            }

            $pairs[$key] = urldecode($value);
        }

        return $pairs;
    }
}
