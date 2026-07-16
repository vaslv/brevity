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
    // After a failed open (corrupt/unreadable file), wait this long before
    // retrying — otherwise a broken database reports on every single click.
    private const REOPEN_BACKOFF_SECONDS = 300;

    private ?int $openFailedAt = null;

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
            latitude: $record->location->latitude,
            longitude: $record->location->longitude,
        );
    }

    private function reader(): ?Reader
    {
        if ($this->reader !== null) {
            return $this->reader;
        }

        $path = (string) config('geo.database_path');

        // A missing file is cheap to re-check every call and lets a freshly
        // installed database be picked up immediately, so it is not backed off.
        if (! is_file($path)) {
            return null;
        }

        // A file that is present but failed to open is corrupt/unreadable:
        // negative-cache the failure so we do not report() on every click.
        if ($this->openFailedAt !== null && now()->timestamp - $this->openFailedAt < self::REOPEN_BACKOFF_SECONDS) {
            return null;
        }

        try {
            return $this->reader = new Reader($path);
        } catch (\Throwable $e) {
            report($e);
            $this->openFailedAt = now()->timestamp;

            return null;
        }
    }
}
