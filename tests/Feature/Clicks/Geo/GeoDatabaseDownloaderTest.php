<?php

namespace Tests\Feature\Clicks\Geo;

use App\Services\Links\Geo\GeoDatabaseDownloader;
use App\Services\Links\Geo\GeoDownloadStatus;
use App\Services\Links\Geo\GeoLocator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PharData;
use Tests\TestCase;

/**
 * Stage 4 of docs/07-plans.md — geo:download-db fetches the MaxMind tarball,
 * unpacks the nested .mmdb and atomically installs it. Exercised with a fake
 * tarball built from the official MaxMind test database, so no network or
 * license key is needed.
 */
class GeoDatabaseDownloaderTest extends TestCase
{
    private string $targetPath;

    private string $workDir;

    public function test_a_failed_download_installs_nothing(): void
    {
        Http::fake(['*' => Http::response('nope', 500)]);

        $result = app(GeoDatabaseDownloader::class)->download();

        $this->assertSame(GeoDownloadStatus::Failed, $result->status);
        $this->assertFileDoesNotExist($this->targetPath);
    }

    public function test_a_transport_error_is_handled_gracefully(): void
    {
        Http::fake(function () {
            throw new ConnectionException('connection refused');
        });

        $result = app(GeoDatabaseDownloader::class)->download();

        $this->assertSame(GeoDownloadStatus::Failed, $result->status);
        $this->assertFileDoesNotExist($this->targetPath);
    }

    public function test_an_archive_without_a_database_fails(): void
    {
        Http::fake(['*' => Http::response($this->makeTarball(withDatabase: false))]);

        $result = app(GeoDatabaseDownloader::class)->download();

        $this->assertSame(GeoDownloadStatus::Failed, $result->status);
        $this->assertFileDoesNotExist($this->targetPath);
    }

    public function test_it_downloads_extracts_and_installs_the_database(): void
    {
        Http::fake(['*' => Http::response($this->makeTarball())]);

        $result = app(GeoDatabaseDownloader::class)->download();

        $this->assertTrue($result->succeeded());
        $this->assertFileExists($this->targetPath);
        // The installed file is a real, readable database.
        $this->assertSame('GB', app(GeoLocator::class)->locate('81.2.69.142')?->countryCode);
    }

    public function test_it_reports_not_configured_without_a_license_key(): void
    {
        config(['geo.license_key' => '']);
        Http::fake();

        $result = app(GeoDatabaseDownloader::class)->download();

        $this->assertSame(GeoDownloadStatus::NotConfigured, $result->status);
        Http::assertNothingSent();
    }

    public function test_it_skips_when_another_download_holds_the_lock(): void
    {
        $lock = Cache::lock('geo:download', 60);
        $this->assertTrue($lock->get());
        Http::fake();

        try {
            $result = app(GeoDatabaseDownloader::class)->download();
        } finally {
            $lock->release();
        }

        $this->assertSame(GeoDownloadStatus::Skipped, $result->status);
        Http::assertNothingSent();
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
    private function makeTarball(bool $withDatabase = true): string
    {
        $scratch = $this->workDir.'/build-'.uniqid();
        $inner = $scratch.'/GeoLite2-City_20260712';
        File::ensureDirectoryExists($inner);
        File::put($inner.'/COPYRIGHT.txt', 'test');

        if ($withDatabase) {
            File::copy(base_path('tests/Fixtures/geo/GeoIP2-City-Test.mmdb'), $inner.'/GeoLite2-City.mmdb');
        }

        $tar = $scratch.'/db.tar';
        (new PharData($tar))->buildFromDirectory($scratch, '#GeoLite2-City_20260712#');
        (new PharData($tar))->compress(\Phar::GZ);

        $bytes = File::get($tar.'.gz');
        File::deleteDirectory($scratch);

        return $bytes;
    }
}
