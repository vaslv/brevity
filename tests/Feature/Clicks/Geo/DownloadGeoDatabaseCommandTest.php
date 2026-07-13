<?php

namespace Tests\Feature\Clicks\Geo;

use App\Jobs\RecordClickJob;
use App\Models\Click;
use App\Models\Link;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PharData;
use Tests\TestCase;

/**
 * Stage 4 (review 2026-07-13, r32) — geo:download-db maps the downloader result
 * to an exit code: success and "another run holds the lock" are a zero exit
 * (deploy scripts treat them as fine), while a real failure and a missing
 * license key exit non-zero.
 */
class DownloadGeoDatabaseCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $targetPath;

    private string $workDir;

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

    protected function setUp(): void
    {
        parent::setUp();

        $this->workDir = sys_get_temp_dir().'/brevity-geo-'.uniqid();
        $this->targetPath = $this->workDir.'/GeoLite2-City.mmdb';
        File::ensureDirectoryExists($this->workDir);

        config([
            'geo.license_key' => 'test-key',
            'geo.database_path' => $this->targetPath,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->workDir);

        parent::tearDown();
    }

    private function makeTarball(): string
    {
        $scratch = $this->workDir.'/build-'.uniqid();
        $inner = $scratch.'/GeoLite2-City_20260712';
        File::ensureDirectoryExists($inner);
        File::copy(base_path('tests/Fixtures/geo/GeoIP2-City-Test.mmdb'), $inner.'/GeoLite2-City.mmdb');

        $tar = $scratch.'/db.tar';
        (new PharData($tar))->buildFromDirectory($scratch, '#GeoLite2-City_20260712#');
        (new PharData($tar))->compress(\Phar::GZ);

        $bytes = File::get($tar.'.gz');
        File::deleteDirectory($scratch);

        return $bytes;
    }
}
