<?php

namespace Tests\Feature\Clicks\Geo;

use App\Jobs\RecordClickJob;
use App\Models\Click;
use App\Models\Link;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\MakesGeoTarball;
use Tests\TestCase;

/**
 * Stage 4 (review 2026-07-13, r32) — geo:download-db maps the downloader result
 * to an exit code: success and "another run holds the lock" are a zero exit
 * (deploy scripts treat them as fine), while a real failure and a missing
 * license key exit non-zero.
 */
class DownloadGeoDatabaseCommandTest extends TestCase
{
    use MakesGeoTarball;
    use RefreshDatabase;

    public function test_a_download_held_by_the_lock_exits_zero(): void
    {
        $lock = Cache::lock('geo:download', 60);
        $this->assertTrue($lock->get());
        Http::fake();

        try {
            $this->artisan('geo:download-db')->assertSuccessful();
        } finally {
            $lock->release();
        }
    }

    public function test_a_failed_download_exits_nonzero(): void
    {
        Http::fake(['*' => Http::response('nope', 500)]);

        $this->artisan('geo:download-db')->assertFailed();
    }

    public function test_a_missing_license_key_exits_nonzero(): void
    {
        config(['geo.license_key' => '']);
        Http::fake();

        $this->artisan('geo:download-db')->assertFailed();
    }

    public function test_a_successful_download_exits_zero(): void
    {
        Http::fake(['*' => Http::response($this->makeTarball())]);

        $this->artisan('geo:download-db')->assertSuccessful();
        $this->assertFileExists($this->targetPath);
    }

    public function test_the_full_download_to_located_click_flow(): void
    {
        Http::fake(['*' => Http::response($this->makeTarball())]);

        // Install the database via the command, then a click geolocates against
        // it through the real singleton locator in the same process.
        $this->artisan('geo:download-db')->assertSuccessful();

        $link = Link::factory()->create();
        $url = Url::factory()->create();
        RecordClickJob::dispatchSync((string) Str::uuid(), $link->id, $url->id, '81.2.69.142', null, 'UA');

        $geo = Click::query()->firstOrFail()->geoLocation;
        $this->assertSame('GB', $geo->country_code);
        $this->assertSame('London', $geo->city);
    }
}
