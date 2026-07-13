<?php

namespace Tests\Feature\Clicks\Geo;

use App\Console\Commands\LocateClicks;
use App\Models\Click;
use App\Models\GeoLocation;
use App\Models\IpAddress;
use App\Services\Links\Geo\GeoLocationResolver;
use App\Services\Links\Geo\GeoLocator;
use App\Services\Links\Geo\ResolvedGeoLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use ReflectionClass;
use ReflectionClassConstant;
use Tests\TestCase;

/**
 * Stage 4 of docs/07-plans.md — geo:locate-clicks backfills geolocation for
 * clicks recorded before geo was enabled. A click whose IP is unknown or was
 * already pruned stays unlocated. Uses the official MaxMind test database.
 */
class LocateClicksCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_click_without_an_ip_stays_unlocated(): void
    {
        // ips:prune already detached the IP: nothing to locate against.
        $click = Click::factory()->create(['ip_address_id' => null]);

        $this->artisan('geo:locate-clicks')
            ->expectsOutputToContain('Located 0 click(s).')
            ->assertSuccessful();

        $this->assertNull($click->refresh()->geo_location_id);
    }

    public function test_an_unknown_ip_stays_unlocated(): void
    {
        $ip = IpAddress::query()->create(['value' => '203.0.113.9']);
        $click = Click::factory()->create(['ip_address_id' => $ip->id]);

        $this->artisan('geo:locate-clicks')
            ->expectsOutputToContain('Located 0 click(s).')
            ->assertSuccessful();

        $this->assertNull($click->refresh()->geo_location_id);
    }

    public function test_it_aborts_early_when_the_database_is_absent(): void
    {
        config(['geo.database_path' => base_path('tests/Fixtures/geo/does-not-exist.mmdb')]);
        $ip = IpAddress::query()->create(['value' => '81.2.69.142']);
        $click = Click::factory()->create(['ip_address_id' => $ip->id]);

        $this->artisan('geo:locate-clicks')
            ->expectsOutputToContain('Geo database not found')
            ->assertSuccessful();

        // No scan happened: the known IP is left unlocated.
        $this->assertNull($click->refresh()->geo_location_id);
    }

    public function test_it_aborts_when_another_run_holds_the_lock(): void
    {
        $lock = Cache::lock('geo:locate-clicks', 60);
        $this->assertTrue($lock->get());

        try {
            $this->artisan('geo:locate-clicks')->assertFailed();
        } finally {
            $lock->release();
        }
    }

    public function test_it_backfills_geolocation_for_a_known_ip(): void
    {
        $ip = IpAddress::query()->create(['value' => '81.2.69.142']);
        $click = Click::factory()->create(['ip_address_id' => $ip->id]);

        $this->artisan('geo:locate-clicks')
            ->expectsOutputToContain('Located 1 click(s).')
            ->assertSuccessful();

        $geo = $click->refresh()->geoLocation;
        $this->assertSame('GB', $geo->country_code);
        $this->assertSame('London', $geo->city);
    }

    public function test_repeated_ips_share_one_dictionary_row(): void
    {
        $ip = IpAddress::query()->create(['value' => '81.2.69.142']);
        $a = Click::factory()->create(['ip_address_id' => $ip->id]);
        $b = Click::factory()->create(['ip_address_id' => $ip->id]);

        $this->artisan('geo:locate-clicks')
            ->expectsOutputToContain('Located 2 click(s).')
            ->assertSuccessful();

        $this->assertSame($a->refresh()->geo_location_id, $b->refresh()->geo_location_id);
        $this->assertSame(1, GeoLocation::query()->count());
    }

    public function test_the_ip_memo_is_capped_to_bound_memory(): void
    {
        $command = new LocateClicks;
        $reflection = new ReflectionClass($command);
        $cap = (new ReflectionClassConstant(LocateClicks::class, 'MEMO_CAP'))->getValue();

        // Fill the memo to its cap, then resolve one more IP.
        $memo = $reflection->getProperty('memo');
        $memo->setValue($command, array_fill(0, $cap, null));

        $locator = new class implements GeoLocator
        {
            public function locate(?string $ip): ?ResolvedGeoLocation
            {
                return null;
            }
        };

        $reflection->getMethod('resolveForIp')
            ->invoke($command, $locator, new GeoLocationResolver, '198.51.100.1');

        // The memo was reset at the cap before adding the new entry.
        $this->assertCount(1, $memo->getValue($command));
    }

    public function test_the_limit_option_bounds_a_run(): void
    {
        $ip = IpAddress::query()->create(['value' => '81.2.69.142']);
        Click::factory()->create(['ip_address_id' => $ip->id]);
        Click::factory()->create(['ip_address_id' => $ip->id]);

        $this->artisan('geo:locate-clicks', ['--limit' => 1])
            ->expectsOutputToContain('Located 1 click(s).')
            ->assertSuccessful();

        // Exactly one located; the run stopped at the limit.
        $this->assertSame(1, Click::query()->whereNotNull('geo_location_id')->count());
        $this->assertSame(1, Click::query()->whereNull('geo_location_id')->count());
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['geo.database_path' => base_path('tests/Fixtures/geo/GeoIP2-City-Test.mmdb')]);
    }
}
