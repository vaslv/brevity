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
}
