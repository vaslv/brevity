<?php

namespace Tests\Feature\Clicks\Geo;

use App\Services\Links\Geo\GeoDatabaseDownloader;
use App\Services\Links\Geo\GeoDownloadStatus;
use App\Services\Links\Geo\GeoLocator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
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

    public function test_a_corrupt_database_in_the_archive_leaves_the_existing_one(): void
    {
        // A previously installed, valid database must survive a bad download.
        File::copy(base_path('tests/Fixtures/geo/GeoIP2-City-Test.mmdb'), $this->targetPath);
        $originalHash = md5_file($this->targetPath);

        Http::fake(['*' => Http::response($this->makeTarball(databaseContents: 'truncated / not a real mmdb'))]);

        $result = app(GeoDatabaseDownloader::class)->download();

        $this->assertSame(GeoDownloadStatus::Failed, $result->status);
        // The install was rejected before overwriting the good database.
        $this->assertSame($originalHash, md5_file($this->targetPath));
    }

    public function test_a_failed_download_installs_nothing(): void
    {
        Http::fake(['*' => Http::response('nope', 500)]);

        $result = app(GeoDatabaseDownloader::class)->download();

        $this->assertSame(GeoDownloadStatus::Failed, $result->status);
        $this->assertFileDoesNotExist($this->targetPath);
    }

    public function test_a_lock_backend_failure_becomes_a_failed_result(): void
    {
        Exceptions::fake();
        // Redis unavailable when acquiring the download lock must not crash the
        // command or the job.
        Cache::shouldReceive('lock')->andThrow(new \RuntimeException('redis is down'));
        Http::fake();

        $result = app(GeoDatabaseDownloader::class)->download();

        $this->assertSame(GeoDownloadStatus::Failed, $result->status);
        Http::assertNothingSent();
        Exceptions::assertReported(fn (\RuntimeException $e): bool => true);
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

    public function test_a_transport_error_never_leaks_the_license_key(): void
    {
        Exceptions::fake();
        config(['geo.license_key' => 'super-secret-key']);

        // Guzzle embeds the full effective URL — including the license_key query
        // parameter — in a transport-error message.
        Http::fake(function () {
            throw new ConnectionException(
                'cURL error 28: timeout for https://download.maxmind.com/app/geoip_download'
                .'?edition_id=GeoLite2-City&license_key=super-secret-key&suffix=tar.gz'
            );
        });

        $result = app(GeoDatabaseDownloader::class)->download();

        $this->assertSame(GeoDownloadStatus::Failed, $result->status);
        $this->assertStringNotContainsString('super-secret-key', $result->message);
        $this->assertStringContainsString('********', $result->message);
        Exceptions::assertReported(
            fn (\RuntimeException $e): bool => ! str_contains($e->getMessage(), 'super-secret-key')
        );
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
     * return its raw bytes. Pass $databaseContents to embed a specific (e.g.
     * corrupt) .mmdb instead of the valid fixture.
     */
    private function makeTarball(bool $withDatabase = true, ?string $databaseContents = null): string
    {
        $scratch = $this->workDir.'/build-'.uniqid();
        $inner = $scratch.'/GeoLite2-City_20260712';
        File::ensureDirectoryExists($inner);
        File::put($inner.'/COPYRIGHT.txt', 'test');

        if ($withDatabase && $databaseContents !== null) {
            File::put($inner.'/GeoLite2-City.mmdb', $databaseContents);
        } elseif ($withDatabase) {
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
