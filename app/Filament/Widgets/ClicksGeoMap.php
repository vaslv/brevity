<?php

namespace App\Filament\Widgets;

use App\Models\Click;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ClicksGeoMap extends Widget
{
    public const DAYS = 30;

    // Cities beyond the cap are the long tail (a bubble per stray click adds
    // noise, not signal), and an unbounded marker list would bloat the page.
    private const MAX_POINTS = 200;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 4;

    protected string $view = 'filament.widgets.clicks-geo-map';

    /**
     * Top located cities by clicks for the window, heaviest first.
     *
     * @return list<array{lat: float, lng: float, label: string, count: int}>
     */
    public function getMarkers(): array
    {
        // The created_at range keeps this on the clicks_created_at index; rows
        // without geo (or with a pre-coordinates tuple) drop out via the join
        // and the latitude filter. Grouping by the dictionary PK lets Postgres
        // select the tuple columns directly (functional dependency). toBase():
        // an aggregate row is not a Click, so it must not hydrate one.
        $rows = Click::query()
            ->join('geo_locations', 'geo_locations.id', '=', 'clicks.geo_location_id')
            ->where('clicks.created_at', '>=', Carbon::today()->subDays(self::DAYS - 1))
            ->whereNotNull('geo_locations.latitude')
            ->groupBy('geo_locations.id')
            ->orderByDesc('clicks_count')
            ->limit(self::MAX_POINTS)
            ->toBase()
            ->get([
                'geo_locations.country_code',
                'geo_locations.region',
                'geo_locations.city',
                'geo_locations.latitude',
                'geo_locations.longitude',
                DB::raw('count(*) as clicks_count'),
            ]);

        return $rows->map(fn (object $row): array => [
            'lat' => (float) $row->latitude,
            'lng' => (float) $row->longitude,
            'label' => $this->markerLabel(
                (string) $row->country_code,
                (string) $row->region,
                (string) $row->city,
            ),
            'count' => (int) $row->clicks_count,
        ])->all();
    }

    /**
     * The finest place name the tuple has, suffixed with the country code:
     * "London, GB", a country-only tuple collapses to just "GB".
     */
    private function markerLabel(string $countryCode, string $region, string $city): string
    {
        $place = $city !== '' ? $city : $region;

        return $place !== '' ? "{$place}, {$countryCode}" : $countryCode;
    }
}
