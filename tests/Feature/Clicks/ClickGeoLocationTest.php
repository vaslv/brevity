<?php

namespace Tests\Feature\Clicks;

use App\Jobs\RecordClickJob;
use App\Models\Click;
use App\Models\GeoLocation;
use App\Models\Link;
use App\Models\Url;
use App\Services\Links\Clicks\ClickRecorder;
use App\Services\Links\Geo\GeoLocator;
use App\Services\Links\Geo\ResolvedGeoLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Stage 4 of docs/07-plans.md — the RecordClickJob geolocates the visitor IP
 * and stamps clicks.geo_location_id via the deduplicated geo_locations
 * dictionary. Geo is best-effort: a click without a location is normal.
 */
class ClickGeoLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_geo_dictionary_failure_leaves_the_click_unlocated(): void
    {
        Log::spy();
        $link = Link::factory()->create();
        $url = Url::factory()->create();

        // A country_code the char(2) column rejects: the dictionary insert
        // throws, but geo is best-effort — the click must still be recorded,
        // unlocated, with a warning.
        $geo = new ResolvedGeoLocation('TOOLONG', 'Region', 'City');

        $click = app(ClickRecorder::class)->record(
            $link,
            (string) Str::uuid(),
            $url->id,
            '81.2.69.142',
            null,
            'UA',
            null,
            null,
            $geo,
        );

        $this->assertNull($click->geo_location_id);
        $this->assertSame(1, Click::query()->count());
        $this->assertSame(0, GeoLocation::query()->count());
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_a_null_location_leaves_the_click_unlocated(): void
    {
        $link = Link::factory()->create();
        $url = Url::factory()->create();

        $click = app(ClickRecorder::class)->record(
            $link,
            (string) Str::uuid(),
            $url->id,
            '203.0.113.9',
            null,
            'UA',
            null,
            null,
            null,
        );

        $this->assertNull($click->geo_location_id);
        $this->assertSame(0, GeoLocation::query()->count());
    }

    public function test_a_real_lookup_geolocates_the_click_end_to_end(): void
    {
        config(['geo.database_path' => base_path('tests/Fixtures/geo/GeoIP2-City-Test.mmdb')]);

        $link = Link::factory()->create();
        $url = Url::factory()->create();

        RecordClickJob::dispatchSync((string) Str::uuid(), $link->id, $url->id, '81.2.69.142', null, 'UA');

        $geo = Click::query()->firstOrFail()->geoLocation;
        $this->assertSame('GB', $geo->country_code);
        $this->assertSame('London', $geo->city);
    }

    public function test_a_resolved_location_is_attached_and_deduplicated(): void
    {
        $link = Link::factory()->create();
        $url = Url::factory()->create();
        $geo = new ResolvedGeoLocation('GB', 'England', 'London');

        $first = app(ClickRecorder::class)->record($link, (string) Str::uuid(), $url->id, '81.2.69.142', null, 'UA', null, null, $geo);
        $second = app(ClickRecorder::class)->record($link, (string) Str::uuid(), $url->id, '81.2.69.142', null, 'UA', null, null, $geo);

        $this->assertNotNull($first->geo_location_id);
        $this->assertSame($first->geo_location_id, $second->geo_location_id);
        $this->assertSame(1, GeoLocation::query()->count());
        $this->assertDatabaseHas('geo_locations', ['country_code' => 'GB', 'region' => 'England', 'city' => 'London']);
    }

    public function test_a_retry_with_a_different_location_keeps_the_original(): void
    {
        $link = Link::factory()->create();
        $url = Url::factory()->create();
        $uuid = (string) Str::uuid();

        $first = app(ClickRecorder::class)->record($link, $uuid, $url->id, '81.2.69.142', null, 'UA', null, null, new ResolvedGeoLocation('GB', 'England', 'London'));
        // Same uuid (a job retry) with a different resolved location: firstOrCreate
        // returns the existing click and must not rewrite its geolocation.
        $second = app(ClickRecorder::class)->record($link, $uuid, $url->id, '81.2.69.142', null, 'UA', null, null, new ResolvedGeoLocation('FR', 'Ile-de-France', 'Paris'));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Click::query()->count());
        $this->assertSame('GB', $second->fresh()->geoLocation->country_code);
    }

    public function test_the_job_geolocates_from_the_visitor_ip(): void
    {
        // Fake the locator so the assertion does not depend on the database file.
        $this->app->instance(GeoLocator::class, new class implements GeoLocator
        {
            public function locate(?string $ip): ?ResolvedGeoLocation
            {
                return $ip === '81.2.69.142' ? new ResolvedGeoLocation('GB', 'England', 'London') : null;
            }
        });

        $link = Link::factory()->create();
        $url = Url::factory()->create();

        RecordClickJob::dispatchSync((string) Str::uuid(), $link->id, $url->id, '81.2.69.142', null, 'UA');

        $this->assertSame('London', Click::query()->firstOrFail()->geoLocation->city);
    }
}
