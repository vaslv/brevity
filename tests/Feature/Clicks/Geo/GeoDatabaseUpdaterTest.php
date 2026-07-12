<?php

namespace Tests\Feature\Clicks\Geo;

use App\Jobs\UpdateGeoDatabaseJob;
use App\Services\Links\Geo\GeoDatabaseDownloader;
use App\Services\Links\Geo\GeoDatabaseUpdater;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PharData;
use Tests\TestCase;

/**
 * Stage 4 of docs/07-plans.md — the geo database is refreshed "by traffic": a
 * click pings the updater, which dispatches a download only when the database
 * has aged past geo.max_age_days. A throttle bounds the check regardless of
 * traffic volume and a consecutive-failure backoff stops hammering MaxMind.
 */
class GeoDatabaseUpdaterTest extends TestCase
{
    private string $targetPath;

    private string $workDir;

    public function test_a_click_ping_dispatches_a_refresh_when_the_database_is_stale(): void
    {
        Queue::fake();

        // No database file on disk => stale.
        app(GeoDatabaseUpdater::class)->pingFromTraffic();

        Queue::assertPushed(UpdateGeoDatabaseJob::class, 1);
    }

    public function test_a_successful_refresh_installs_the_database_and_clears_the_failure_backoff(): void
    {
        Cache::put('geo:update:failures', 2, now()->addDay());
        Cache::put('geo:update:last-failure', now()->timestamp, now()->addDay());
        Http::fake(['*' => Http::response($this->makeTarball())]);

        app(GeoDatabaseUpdater::class)->refreshIfStale();

        $this->assertFileExists($this->targetPath);
        $this->assertNull(Cache::get('geo:update:failures'));
        $this->assertNull(Cache::get('geo:update:last-failure'));
    }

    public function test_it_backs_off_after_repeated_download_failures(): void
    {
        Http::fake(['*' => Http::response('nope', 500)]);
        $updater = app(GeoDatabaseUpdater::class);

        // Three failures trip the backoff; a fourth refresh must send nothing.
        $updater->refreshIfStale();
        $updater->refreshIfStale();
        $updater->refreshIfStale();

        $this->assertFalse($updater->shouldRefresh());

        $updater->refreshIfStale();
        Http::assertSentCount(3);
    }

    public function test_it_does_not_refresh_when_the_database_is_fresh(): void
    {
        Queue::fake();
        File::put($this->targetPath, 'a freshly written database');

        app(GeoDatabaseUpdater::class)->pingFromTraffic();

        Queue::assertNothingPushed();
    }

    public function test_it_does_not_refresh_without_a_license_key(): void
    {
        config(['geo.license_key' => '']);
        Queue::fake();

        // The file is missing (stale), but geo is not configured to download.
        app(GeoDatabaseUpdater::class)->pingFromTraffic();

        Queue::assertNothingPushed();
    }

    public function test_the_ping_is_throttled_to_one_dispatch_per_interval(): void
    {
        Queue::fake();

        app(GeoDatabaseUpdater::class)->pingFromTraffic();
        app(GeoDatabaseUpdater::class)->pingFromTraffic();

        // The second click of the interval short-circuits before dispatching.
        Queue::assertPushed(UpdateGeoDatabaseJob::class, 1);
    }

    public function test_the_ping_never_throws_when_the_freshness_check_fails(): void
    {
        Queue::fake();

        // A collaborator that blows up must never surface to the click job: a
        // throw here would fail RecordClickJob after the callback was dispatched
        // and re-deliver it on retry.
        $this->mock(GeoDatabaseDownloader::class)
            ->shouldReceive('isConfigured')
            ->andThrow(new \RuntimeException('geo backend down'));

        app(GeoDatabaseUpdater::class)->pingFromTraffic();

        Queue::assertNothingPushed();
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

    /**
     * Build a MaxMind-shaped tar.gz (a dated directory holding the .mmdb) and
     * return its raw bytes.
     */
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
