<?php

namespace App\Services\Links\Geo;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;

/**
 * Resolves IPs against a local MaxMind GeoLite2-City database (geoip2/geoip2).
 *
 * The Reader is opened lazily and cached for the life of the Octane worker: the
 * database is shared global state, not request state, so caching it is safe and
 * avoids re-parsing the file metadata on every click. A missing database is not
 * cached — resolution keeps returning null until geo:download-db lands the file,
 * after which the next lookup opens it. A worker keeps using the database
 * version present when it first opened until the worker restarts (geo data
 * changes slowly; acceptable).
 */
class MaxMindGeoLocator implements GeoLocator
{
    private ?Reader $reader = null;

    public function locate(?string $ip): ?ResolvedGeoLocation
    {
        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $reader = $this->reader();

        if ($reader === null) {
            return null;
        }

        try {
            $record = $reader->city($ip);
        } catch (AddressNotFoundException) {
            // A normal outcome: the IP is not in the database.
            return null;
        } catch (\Throwable $e) {
            // A corrupt/unreadable database — report but never break recording.
            report($e);

            return null;
        }

        $countryCode = $record->country->isoCode;

        // No country = nothing worth storing; keep the click unlocated.
        if ($countryCode === null) {
            return null;
        }

        return new ResolvedGeoLocation(
            countryCode: $countryCode,
            region: $record->mostSpecificSubdivision->name ?? '',
            city: $record->city->name ?? '',
        );
    }

    private function reader(): ?Reader
    {
        if ($this->reader !== null) {
            return $this->reader;
        }

        $path = (string) config('geo.database_path');

        if (! is_file($path)) {
            return null;
        }

        try {
            return $this->reader = new Reader($path);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
