<?php

namespace App\Console\Commands;

use App\Models\Click;
use App\Models\GeoLocation;
use App\Services\Links\Geo\GeoLocationResolver;
use App\Services\Links\Geo\GeoLocator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

#[Signature('geo:backfill-coordinates')]
#[Description('Backfill coordinates for geo_locations rows created before coordinates were captured. Each row is resolved through a recent related click IP against the local MaxMind database; the coordinates are taken only when the IP still resolves to the same tuple. Idempotent — safe to run repeatedly.')]
class BackfillGeoCoordinates extends Command
{
    // Distinct recent IPs tried per row: the first one may have been reassigned
    // to another city since the tuple was created, so try a few before giving
    // up. Sourced from the latest clicks — recent IPs are least likely to have
    // drifted away from the tuple.
    private const CANDIDATE_IPS = 3;

    private const CHUNK = 200;

    private const LOCK_SECONDS = 3600;

    public function handle(GeoLocator $geoLocator, GeoLocationResolver $resolver): int
    {
        // Without a database every lookup returns null; bail early rather than
        // scan the dictionary for nothing. Not an error, just nothing to do.
        if (! is_file((string) config('geo.database_path'))) {
            $this->warn('Geo database not found; run geo:download-db first. Nothing to backfill.');

            return self::SUCCESS;
        }

        $lock = Cache::lock('geo:backfill-coordinates', self::LOCK_SECONDS);

        if (! $lock->get()) {
            $this->warn('Another geo:backfill-coordinates run is in progress; aborting.');

            return self::FAILURE;
        }

        $filled = 0;

        try {
            // Keyset by id: updating latitude (the filtered column) is safe —
            // the window advances by id, so rows are neither skipped nor
            // revisited.
            GeoLocation::query()
                ->whereNull('latitude')
                ->chunkById(self::CHUNK, function (Collection $locations) use ($geoLocator, $resolver, &$filled): void {
                    /** @var GeoLocation $location */
                    foreach ($locations as $location) {
                        if ($this->backfillLocation($geoLocator, $resolver, $location)) {
                            $filled++;
                        }
                    }
                });
        } finally {
            $lock->release();
        }

        $this->info("Backfilled coordinates for {$filled} geo location(s).");

        return self::SUCCESS;
    }

    private function backfillLocation(GeoLocator $geoLocator, GeoLocationResolver $resolver, GeoLocation $location): bool
    {
        $tuple = $location->only(['country_code', 'region', 'city']);

        // Latest clicks first; unique() collapses an IP that produced several
        // clicks so the window always holds distinct candidates.
        $candidateIps = Click::query()
            ->where('geo_location_id', $location->id)
            ->join('ip_addresses', 'ip_addresses.id', '=', 'clicks.ip_address_id')
            ->orderByDesc('clicks.id')
            ->limit(self::CANDIDATE_IPS * 10)
            ->pluck('ip_addresses.value')
            ->unique()
            ->take(self::CANDIDATE_IPS);

        foreach ($candidateIps as $ip) {
            $resolved = $geoLocator->locate($ip);

            if ($resolved === null || $resolved->latitude === null || $resolved->longitude === null) {
                continue;
            }

            // The IP may have been reassigned since the tuple was created (or
            // the database updated): only coordinates that still resolve to
            // this exact tuple are trustworthy.
            if ($resolver->tupleAttributes($resolved) !== $tuple) {
                continue;
            }

            $location->update([
                'latitude' => $resolved->latitude,
                'longitude' => $resolved->longitude,
            ]);

            return true;
        }

        // No candidate resolved back to the tuple (IPs pruned, dropped from the
        // database, or reassigned): the row stays without coordinates and will
        // not appear on the map.
        return false;
    }
}
