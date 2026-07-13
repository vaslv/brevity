<?php

namespace App\Services\Links\Geo;

use App\Models\GeoLocation;
use Illuminate\Support\Facades\Log;

/**
 * Resolves a (country, region, city) tuple to a geo_locations id, deduplicating
 * the dictionary. Shared by click recording and the geo:locate-clicks backfill.
 * Geo is best-effort enrichment: any failure logs a warning and returns null so
 * the caller records/keeps the click unlocated rather than dropping it.
 */
class GeoLocationResolver
{
    // geo_locations.region / .city are varchar(128); geo names come from MaxMind
    // (bounded), but cap defensively so a lookup can never overflow the column.
    private const MAX_NAME_CHARS = 128;

    public function resolveId(?ResolvedGeoLocation $geo): ?int
    {
        if ($geo === null) {
            return null;
        }

        // Normalize and guard the char(2) column: uppercase so 'de' and 'DE' do
        // not split the tuple, and reject anything that is not exactly two ASCII
        // letters (an unexpected source, empty, or an over-long value) rather
        // than truncate it into a wrong country. An invalid code leaves the
        // click unlocated, like a null location — not an error, so no warning.
        $countryCode = mb_strtoupper($geo->countryCode);

        if (preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
            return null;
        }

        $attributes = [
            'country_code' => $countryCode,
            'region' => mb_substr($geo->region, 0, self::MAX_NAME_CHARS),
            'city' => mb_substr($geo->city, 0, self::MAX_NAME_CHARS),
        ];

        // SELECT-first (locations recur heavily across clicks); createOrFirst is
        // race-safe on the UNIQUE tuple for the miss.
        try {
            return GeoLocation::query()->where($attributes)->value('id')
                ?? GeoLocation::query()->createOrFirst($attributes)->id;
        } catch (\Throwable $e) {
            report($e);
            Log::warning('Failed to resolve click geolocation; leaving the click unlocated.', [
                'country_code' => $geo->countryCode,
            ]);

            return null;
        }
    }
}
