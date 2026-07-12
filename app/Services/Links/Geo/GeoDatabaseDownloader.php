<?php

namespace App\Services\Links\Geo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PharData;
use RecursiveIteratorIterator;

/**
 * Downloads and atomically installs the MaxMind GeoLite2-City database
 * (a .tar.gz that unpacks to a dated directory holding the .mmdb). Shared by
 * the geo:download-db command and the traffic-triggered auto-update, so it owns
 * the lock: any caller is serialized and never collides on the temp files or
 * the target. A running Octane worker keeps its already-open Reader until it
 * restarts (rename leaves the old inode readable) — acceptable, geo data moves
 * slowly.
 */
class GeoDatabaseDownloader
{
    private const LOCK_KEY = 'geo:download';

    private const LOCK_SECONDS = 1800;

    public function download(): GeoDownloadResult
    {
        if (! $this->isConfigured()) {
            return GeoDownloadResult::notConfigured();
        }

        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS);

        if (! $lock->get()) {
            return GeoDownloadResult::skipped('Another geo database download is in progress.');
        }

        try {
            return $this->run();
        } finally {
            $lock->release();
        }
    }

    public function isConfigured(): bool
    {
        return (string) config('geo.license_key') !== '';
    }

    /**
     * Path (a phar:// URL) of the first .mmdb entry inside the archive, or null.
     */
    private function findDatabaseEntry(string $tarPath): ?string
    {
        foreach (new RecursiveIteratorIterator(new PharData($tarPath)) as $file) {
            if (strtolower($file->getExtension()) === 'mmdb') {
                return $file->getPathname();
            }
        }

        return null;
    }

    private function run(): GeoDownloadResult
    {
        $targetPath = (string) config('geo.database_path');
        $dir = dirname($targetPath);
        File::ensureDirectoryExists($dir);

        // Temp paths live beside the target so the final move is a same-filesystem
        // rename (atomic). The lock guarantees a single writer, so fixed names are
        // safe.
        $tarPath = $dir.'/.geoip-download.tar.gz';
        $tmpDbPath = $dir.'/.geoip-download.mmdb';

        try {
            $response = Http::timeout(120)->get((string) config('geo.download_url'), [
                'edition_id' => (string) config('geo.edition'),
                'license_key' => (string) config('geo.license_key'),
                'suffix' => 'tar.gz',
            ]);

            if ($response->failed()) {
                return GeoDownloadResult::failed('Download failed: HTTP '.$response->status().'.');
            }

            File::put($tarPath, $response->body());

            // Extract ONLY the .mmdb by streaming its archive entry to our own
            // temp path — other (attacker-controlled) entry paths are never
            // written to disk, so a crafted archive cannot traverse out of the
            // target directory. A corrupt archive throws and is handled below.
            $entry = $this->findDatabaseEntry($tarPath);

            if ($entry === null) {
                return GeoDownloadResult::failed('No .mmdb file was found in the archive.');
            }

            File::copy($entry, $tmpDbPath);
            File::move($tmpDbPath, $targetPath);

            return GeoDownloadResult::success('Geo database installed at '.$targetPath.'.');
        } catch (\Throwable $e) {
            // Transport error, write failure, corrupt archive: never crash the
            // command/job, and leave the existing database untouched.
            report($e);

            return GeoDownloadResult::failed('Geo database download failed: '.$e->getMessage());
        } finally {
            File::delete([$tarPath, $tmpDbPath]);
        }
    }
}
