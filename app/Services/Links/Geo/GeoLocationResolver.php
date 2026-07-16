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

        $attributes = $this->tupleAttributes($geo);

        if ($attributes === null) {
            return null;
        }

        // SELECT-first (locations recur heavily across clicks); createOrFirst is
        // race-safe on the UNIQUE tuple for the miss. Coordinates are stored
        // only on create — an existing tuple keeps what it has, and rows
        // predating coordinates are filled by geo:backfill-coordinates.
        try {
            return GeoLocation::query()->where($attributes)->value('id')
                ?? GeoLocation::query()->createOrFirst($attributes, [
                    'latitude' => $geo->latitude,
                    'longitude' => $geo->longitude,
                ])->id;
        } catch (\Throwable $e) {
            report($e);
            Log::warning('Failed to resolve click geolocation; leaving the click unlocated.', [
                'country_code' => $geo->countryCode,
            ]);

            return null;
        }
    }

    /**
     * Normalize a resolved location to its dictionary tuple, or null when the
     * country code cannot form a valid tuple. Uppercases the code so 'de' and
     * 'DE' do not split the tuple, and rejects anything that is not exactly two
     * ASCII letters (an unexpected source, empty, or an over-long value) rather
     * than truncate it into a wrong country. Shared by resolveId and
     * geo:backfill-coordinates so both sides compare tuples identically.
     *
     * @return array{country_code: string, region: string, city: string}|null
     */
    public function tupleAttributes(ResolvedGeoLocation $geo): ?array
    {
        $countryCode = mb_strtoupper($geo->countryCode);

        if (preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
            return null;
        }

        return [
            'country_code' => $countryCode,
            'region' => mb_substr($geo->region, 0, self::MAX_NAME_CHARS),
            'city' => mb_substr($geo->city, 0, self::MAX_NAME_CHARS),
        ];
    }
}
