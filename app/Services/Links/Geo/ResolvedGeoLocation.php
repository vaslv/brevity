<?php

namespace App\Services\Links\Geo;

/**
 * A resolved click location. region/city are '' (never null) when the database
 * has no finer detail, mirroring the geo_locations dictionary so the tuple
 * deduplicates cleanly. Coordinates are best-effort: null when the database
 * record carries none.
 */
readonly class ResolvedGeoLocation
{
    public function __construct(
        public string $countryCode,
        public string $region = '',
        public string $city = '',
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {}
}
