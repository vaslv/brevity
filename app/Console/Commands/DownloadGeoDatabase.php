<?php

namespace App\Console\Commands;

use App\Services\Links\Geo\GeoDatabaseDownloader;
use App\Services\Links\Geo\GeoDownloadStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('geo:download-db')]
#[Description('Download and install the MaxMind GeoLite2-City database. Initial setup after deploy and a manual refresh; the traffic-triggered auto-update keeps it current afterwards.')]
class DownloadGeoDatabase extends Command
{
    public function handle(GeoDatabaseDownloader $downloader): int
    {
        $result = $downloader->download();

        if ($result->succeeded()) {
            $this->info($result->message);

            return self::SUCCESS;
        }

        // Another run is already downloading — not an error for a manual call.
        if ($result->status === GeoDownloadStatus::Skipped) {
            $this->warn($result->message);

            return self::SUCCESS;
        }

        $this->error($result->message);

        return self::FAILURE;
    }
}
