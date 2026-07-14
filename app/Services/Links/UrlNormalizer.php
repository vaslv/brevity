<?php

namespace App\Services\Links;

use League\Uri\Modifier;

/**
 * Canonical form for a destination URL. The same normalization runs at
 * validation time (to byte-cap the value that actually reaches the column) and
 * at persist time (to key the urls.value UNIQUE index), so both must agree —
 * hence a single source of truth.
 */
final class UrlNormalizer
{
    /**
     * Max byte length for a normalized destination URL. `urls.value` carries a
     * UNIQUE btree index (~2704-byte key limit); an over-long value is rejected
     * (422) rather than truncated, since truncating a redirect target corrupts
     * it. Measured AFTER normalization — percent-encoding can triple the size.
     */
    public const MAX_BYTES = 2000;

    /**
     * Percent-encode, lowercase the host, and sort the query. Note the result
     * can be several times longer than the raw input for internationalized
     * paths (Cyrillic/CJK expand under percent-encoding), so callers that cap
     * length must measure the normalized value, not the raw one.
     */
    public static function normalize(string $rawUrl): string
    {
        return Modifier::wrap($rawUrl)
            ->normalize()
            ->sortQuery()
            ->toString();
    }
}
