<?php

namespace Tests\Feature\Clicks\Geo;

use App\Models\GeoLocation;
use App\Services\Links\Geo\GeoLocationResolver;
use App\Services\Links\Geo\ResolvedGeoLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stage 4 (review 2026-07-13, r30) — the resolver deduplicates a
 * (country, region, city) tuple to a geo_locations id. It normalizes the
 * country code to uppercase so case variants share one row, and rejects a code
 * that is not exactly two ASCII letters so a bad source cannot overflow the
 * char(2) column or split the dictionary.
 */
class GeoLocationResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinates_are_stored_on_create_and_kept_on_later_resolves(): void
    {
        $resolver = app(GeoLocationResolver::class);

        $id = $resolver->resolveId(new ResolvedGeoLocation('GB', 'England', 'London', 51.5142, -0.0931));

        $row = GeoLocation::query()->findOrFail($id);
        $this->assertEqualsWithDelta(51.5142, $row->latitude, 0.0001);
        $this->assertEqualsWithDelta(-0.0931, $row->longitude, 0.0001);

        // The tuple already exists: a later resolve with drifted coordinates
        // (a MaxMind database update) reuses the row and keeps its coordinates.
        $again = $resolver->resolveId(new ResolvedGeoLocation('GB', 'England', 'London', 51.5, -0.1));

        $this->assertSame($id, $again);
        $this->assertEqualsWithDelta(51.5142, $row->refresh()->latitude, 0.0001);
    }

    public function test_coordinates_may_be_absent(): void
    {
        $id = app(GeoLocationResolver::class)->resolveId(new ResolvedGeoLocation('JP'));

        $row = GeoLocation::query()->findOrFail($id);
        $this->assertNull($row->latitude);
        $this->assertNull($row->longitude);
    }

    public function test_country_only_locations_share_one_dictionary_row(): void
    {
        $resolver = app(GeoLocationResolver::class);

        // Empty region/city (a country-only lookup) must be '' not NULL, so the
        // UNIQUE tuple dedupes rather than inserting a new row every time.
        $a = $resolver->resolveId(new ResolvedGeoLocation('JP'));
        $b = $resolver->resolveId(new ResolvedGeoLocation('JP'));

        $this->assertNotNull($a);
        $this->assertSame($a, $b);
        $this->assertSame(1, GeoLocation::query()->count());
        $this->assertDatabaseHas('geo_locations', ['country_code' => 'JP', 'region' => '', 'city' => '']);
    }

    public function test_it_rejects_a_country_code_that_is_not_two_letters(): void
    {
        $resolver = app(GeoLocationResolver::class);

        $this->assertNull($resolver->resolveId(new ResolvedGeoLocation('TOOLONG', 'R', 'C')));
        $this->assertNull($resolver->resolveId(new ResolvedGeoLocation('1', 'R', 'C')));
        $this->assertNull($resolver->resolveId(new ResolvedGeoLocation('U1', 'R', 'C')));
        $this->assertNull($resolver->resolveId(new ResolvedGeoLocation('', 'R', 'C')));

        $this->assertSame(0, GeoLocation::query()->count());
    }

    public function test_it_uppercases_the_country_code_so_case_variants_dedupe(): void
    {
        $resolver = app(GeoLocationResolver::class);

        $lower = $resolver->resolveId(new ResolvedGeoLocation('de', 'Bavaria', 'Munich'));
        $upper = $resolver->resolveId(new ResolvedGeoLocation('DE', 'Bavaria', 'Munich'));

        $this->assertNotNull($lower);
        $this->assertSame($lower, $upper);
        $this->assertSame(1, GeoLocation::query()->count());
        $this->assertSame('DE', GeoLocation::query()->firstOrFail()->country_code);
    }

    public function test_overlong_region_and_city_names_are_capped_at_128_chars(): void
    {
        // A name longer than the varchar(128) column must be capped, not rejected
        // or left to overflow. Multibyte to prove the cap counts characters.
        $region = str_repeat('ж', 200);
        $city = str_repeat('x', 200);

        $id = app(GeoLocationResolver::class)->resolveId(new ResolvedGeoLocation('RU', $region, $city));

        $this->assertNotNull($id);
        $row = GeoLocation::query()->findOrFail($id);
        $this->assertSame(128, mb_strlen($row->region));
        $this->assertSame(128, mb_strlen($row->city));
    }
}
