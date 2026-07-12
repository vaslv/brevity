<?php

namespace App\Services\Links\Geo;

/**
 * A resolved click location. region/city are '' (never null) when the database
 * has no finer detail, mirroring the geo_locations dictionary so the tuple
 * deduplicates cleanly.
 */
readonly class ResolvedGeoLocation
{
    public function __construct(
        public string $countryCode,
        public string $region = '',
        public string $city = '',
    ) {}
}
