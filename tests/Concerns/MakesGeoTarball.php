<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\File;
use PharData;

/**
 * Shared geo test scaffolding: a temp working directory that also becomes the
 * geo database path, plus a builder for a MaxMind-shaped tar.gz. setUp/tearDown
 * are booted automatically by Laravel's setUpTraits() (setUp{TraitName}).
 */
trait MakesGeoTarball
{
    protected string $targetPath;

    protected string $workDir;

    /**
     * Build a MaxMind-shaped tar.gz (a dated directory holding the .mmdb) and
     * return its raw bytes. Pass $databaseContents to embed a specific (e.g.
     * corrupt) .mmdb instead of the valid fixture.
     */
    protected function makeTarball(bool $withDatabase = true, ?string $databaseContents = null): string
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

    protected function setUpMakesGeoTarball(): void
    {
        $this->workDir = sys_get_temp_dir().'/brevity-geo-'.uniqid();
        $this->targetPath = $this->workDir.'/GeoLite2-City.mmdb';
        File::ensureDirectoryExists($this->workDir);

        config([
            'geo.license_key' => 'test-key',
            'geo.database_path' => $this->targetPath,
        ]);
    }

    protected function tearDownMakesGeoTarball(): void
    {
        File::deleteDirectory($this->workDir);
    }
}
