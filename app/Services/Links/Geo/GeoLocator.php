<?php

namespace App\Services\Links\Geo;

interface GeoLocator
{
    /**
     * Resolve an IP to a location, or null when it cannot be resolved (no
     * database, an unknown or invalid IP, a read error). Resolution must never
     * throw — a click is recorded with or without a location.
     */
    public function locate(?string $ip): ?ResolvedGeoLocation;
}
