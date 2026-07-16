<?php

namespace Tests\Feature\Clicks\Geo;

use App\Services\Links\Geo\MaxMindGeoLocator;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;
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

    public function test_a_corrupt_database_resolves_to_null_and_reports_once(): void
    {
        Exceptions::fake();
        $path = sys_get_temp_dir().'/brevity-geo-corrupt-'.uniqid().'.mmdb';
        File::put($path, 'this is not a maxmind database');
        config(['geo.database_path' => $path]);

        try {
            $locator = new MaxMindGeoLocator;

            // Three clicks against the same broken database.
            $this->assertNull($locator->locate('81.2.69.142'));
            $this->assertNull($locator->locate('81.2.69.142'));
            $this->assertNull($locator->locate('81.2.69.142'));

            // The open failure is reported once, not once per click (the negative
            // cache suppresses the per-click report storm).
            Exceptions::assertReportedCount(1);
        } finally {
            File::delete($path);
        }
    }

    public function test_a_known_ip_resolves_to_country_region_city(): void
    {
        $result = $this->locatorWithFixture()->locate('81.2.69.142');

        $this->assertNotNull($result);
        $this->assertSame('GB', $result->countryCode);
        $this->assertSame('England', $result->region);
        $this->assertSame('London', $result->city);
    }

    public function test_a_known_ip_resolves_with_coordinates(): void
    {
        $result = $this->locatorWithFixture()->locate('81.2.69.142');

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(51.5142, $result->latitude, 0.001);
        $this->assertEqualsWithDelta(-0.0931, $result->longitude, 0.001);
    }

    public function test_a_known_ipv6_address_resolves(): void
    {
        // Production traffic includes IPv6; 2001:218::/32 is a country-only
        // (JP) network in the fixture, so region/city coalesce to ''.
        $result = $this->locatorWithFixture()->locate('2001:218::1');

        $this->assertNotNull($result);
        $this->assertSame('JP', $result->countryCode);
        $this->assertSame('', $result->region);
        $this->assertSame('', $result->city);
    }

    public function test_a_missing_database_is_not_cached_and_is_picked_up_once_installed(): void
    {
        $path = sys_get_temp_dir().'/brevity-geo-late-'.uniqid().'.mmdb';
        config(['geo.database_path' => $path]);
        $locator = new MaxMindGeoLocator;

        // Absent at first: no location, and (unlike a corrupt file) not cached.
        $this->assertNull($locator->locate('81.2.69.142'));

        // Installed mid-life by geo:download-db: the same worker's locator opens
        // it on the very next call.
        File::copy(base_path(self::FIXTURE), $path);

        try {
            $this->assertSame('GB', $locator->locate('81.2.69.142')?->countryCode);
        } finally {
            File::delete($path);
        }
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
