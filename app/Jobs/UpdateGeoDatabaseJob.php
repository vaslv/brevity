<?php

namespace App\Jobs;

use App\Services\Links\Geo\GeoDatabaseUpdater;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Refreshes the geo database when it has aged out (stage 4). Dispatched by
 * traffic (GeoDatabaseUpdater::pingFromTraffic), never by cron, so a live but
 * low-traffic deployment still keeps the database current without a scheduler.
 */
class UpdateGeoDatabaseJob implements ShouldQueue
{
    use Queueable;

    public function handle(GeoDatabaseUpdater $updater): void
    {
        $updater->refreshIfStale();
    }
}
