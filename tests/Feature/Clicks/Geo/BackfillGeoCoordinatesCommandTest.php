<?php

namespace Tests\Feature\Clicks\Geo;

use App\Models\Click;
use App\Models\GeoLocation;
use App\Models\IpAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * geo:backfill-coordinates fills coordinates for dictionary rows created before
 * coordinates were captured, by re-resolving a recent related click IP against
 * the local MaxMind database. Coordinates are taken only when the IP still
 * resolves to the same tuple, so a reassigned IP cannot plant a wrong point on
 * the map. Uses the official MaxMind test database (81.2.69.142 → GB/England/
 * London at ~51.5142,-0.0931).
 */
class BackfillGeoCoordinatesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_aborts_early_when_the_database_is_absent(): void
    {
        config(['geo.database_path' => base_path('tests/Fixtures/geo/does-not-exist.mmdb')]);
        $location = $this->locationWithoutCoordinates(['country_code' => 'GB', 'region' => 'England', 'city' => 'London']);

        $this->artisan('geo:backfill-coordinates')
            ->expectsOutputToContain('Geo database not found')
            ->assertSuccessful();

        $this->assertNull($location->refresh()->latitude);
    }

    public function test_it_aborts_when_another_run_holds_the_lock(): void
    {
        $lock = Cache::lock('geo:backfill-coordinates', 60);
        $this->assertTrue($lock->get());

        try {
            $this->artisan('geo:backfill-coordinates')->assertFailed();
        } finally {
            $lock->release();
        }
    }

    public function test_it_fills_coordinates_when_the_ip_still_resolves_to_the_tuple(): void
    {
        $location = $this->locationWithoutCoordinates(['country_code' => 'GB', 'region' => 'England', 'city' => 'London']);
        $ip = IpAddress::query()->create(['value' => '81.2.69.142']);
        Click::factory()->create(['geo_location_id' => $location->id, 'ip_address_id' => $ip->id]);

        $this->artisan('geo:backfill-coordinates')
            ->expectsOutputToContain('Backfilled coordinates for 1 geo location(s).')
            ->assertSuccessful();

        $location->refresh();
        $this->assertEqualsWithDelta(51.5142, $location->latitude, 0.001);
        $this->assertEqualsWithDelta(-0.0931, $location->longitude, 0.001);
    }

    public function test_it_leaves_rows_that_already_have_coordinates_untouched(): void
    {
        $location = GeoLocation::factory()->create([
            'country_code' => 'GB',
            'region' => 'England',
            'city' => 'London',
            'latitude' => 10.0,
            'longitude' => 20.0,
        ]);
        $ip = IpAddress::query()->create(['value' => '81.2.69.142']);
        Click::factory()->create(['geo_location_id' => $location->id, 'ip_address_id' => $ip->id]);

        $this->artisan('geo:backfill-coordinates')
            ->expectsOutputToContain('Backfilled coordinates for 0 geo location(s).')
            ->assertSuccessful();

        // Existing coordinates are not "refreshed" from the database.
        $this->assertEqualsWithDelta(10.0, $location->refresh()->latitude, 0.0001);
    }

    public function test_it_skips_a_row_whose_ip_resolves_to_a_different_tuple(): void
    {
        // The tuple says Tokyo, but its recorded IP resolves to London: the IP
        // was reassigned (or the tuple predates a database correction), so its
        // coordinates must not be planted on Tokyo.
        $location = $this->locationWithoutCoordinates(['country_code' => 'JP', 'region' => 'Tokyo', 'city' => 'Tokyo']);
        $ip = IpAddress::query()->create(['value' => '81.2.69.142']);
        Click::factory()->create(['geo_location_id' => $location->id, 'ip_address_id' => $ip->id]);

        $this->artisan('geo:backfill-coordinates')
            ->expectsOutputToContain('Backfilled coordinates for 0 geo location(s).')
            ->assertSuccessful();

        $this->assertNull($location->refresh()->latitude);
    }

    public function test_it_skips_a_row_without_related_click_ips(): void
    {
        // All related clicks lost their IP to ips:prune (or never had one).
        $location = $this->locationWithoutCoordinates(['country_code' => 'GB', 'region' => 'England', 'city' => 'London']);
        Click::factory()->create(['geo_location_id' => $location->id, 'ip_address_id' => null]);

        $this->artisan('geo:backfill-coordinates')
            ->expectsOutputToContain('Backfilled coordinates for 0 geo location(s).')
            ->assertSuccessful();

        $this->assertNull($location->refresh()->latitude);
    }

    public function test_it_tries_a_later_candidate_ip_when_the_first_does_not_resolve(): void
    {
        $location = $this->locationWithoutCoordinates(['country_code' => 'GB', 'region' => 'England', 'city' => 'London']);
        $resolvable = IpAddress::query()->create(['value' => '81.2.69.142']);
        $unknown = IpAddress::query()->create(['value' => '203.0.113.9']);

        // The most recent click's IP is unknown to the database; the older one
        // still resolves. Candidates are tried latest-first.
        Click::factory()->create(['geo_location_id' => $location->id, 'ip_address_id' => $resolvable->id]);
        Click::factory()->create(['geo_location_id' => $location->id, 'ip_address_id' => $unknown->id]);

        $this->artisan('geo:backfill-coordinates')
            ->expectsOutputToContain('Backfilled coordinates for 1 geo location(s).')
            ->assertSuccessful();

        $this->assertEqualsWithDelta(51.5142, $location->refresh()->latitude, 0.001);
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['geo.database_path' => base_path('tests/Fixtures/geo/GeoIP2-City-Test.mmdb')]);
    }

    /**
     * @param  array{country_code: string, region: string, city: string}  $tuple
     */
    private function locationWithoutCoordinates(array $tuple): GeoLocation
    {
        return GeoLocation::factory()->create([...$tuple, 'latitude' => null, 'longitude' => null]);
    }
}
