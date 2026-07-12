<?php

namespace Tests\Feature\Clicks\Geo;

use App\Services\Links\Geo\MaxMindGeoLocator;
use Tests\TestCase;

/**
 * Stage 4 of docs/07-plans.md — the GeoLocator resolves a click IP to a
 * country/region/city against a local MaxMind database, and fails closed
 * (returns null, never throws) on every unresolved or error case so click
 * recording is never broken. Exercised against the official MaxMind test
 * database (tests/Fixtures/geo).
 */
class MaxMindGeoLocatorTest extends TestCase
{
    private const FIXTURE = 'tests/Fixtures/geo/GeoIP2-City-Test.mmdb';

    public function test_a_known_ip_resolves_to_country_region_city(): void
    {
        $result = $this->locatorWithFixture()->locate('81.2.69.142');

        $this->assertNotNull($result);
        $this->assertSame('GB', $result->countryCode);
        $this->assertSame('England', $result->region);
        $this->assertSame('London', $result->city);
    }

    public function test_a_missing_database_resolves_to_null(): void
    {
        config(['geo.database_path' => base_path('tests/Fixtures/geo/does-not-exist.mmdb')]);

        $this->assertNull((new MaxMindGeoLocator)->locate('81.2.69.142'));
    }

    public function test_a_null_ip_resolves_to_null(): void
    {
        $this->assertNull($this->locatorWithFixture()->locate(null));
    }

    public function test_an_invalid_ip_string_resolves_to_null(): void
    {
        $this->assertNull($this->locatorWithFixture()->locate('not-an-ip'));
    }

    public function test_an_unknown_ip_resolves_to_null(): void
    {
        // 203.0.113.0/24 (TEST-NET-3) is absent from the database.
        $this->assertNull($this->locatorWithFixture()->locate('203.0.113.9'));
    }

    private function locatorWithFixture(): MaxMindGeoLocator
    {
        config(['geo.database_path' => base_path(self::FIXTURE)]);

        return new MaxMindGeoLocator;
    }
}
