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

    // The download does an HTTP fetch of a ~50 MB archive
    // (GeoDatabaseDownloader uses Http::timeout(120)) plus extraction. This
    // timeout gives it room to finish; it runs on the dedicated supervisor-geo
    // pool whose worker timeout (330) clears this, and both sit below the queue
    // retry_after (360) so a still-running job is never released and re-reserved.
    public int $timeout = 300;

    public function __construct()
    {
        // Route to the dedicated geo pool: a rare, long-running download must not
        // occupy a click-recording worker, and supervisor-1's 60s timeout would
        // cut a slow download short (see config/horizon.php supervisor-geo).
        $this->onQueue('geo');
    }

    public function handle(GeoDatabaseUpdater $updater): void
    {
        $updater->refreshIfStale();
    }
}
