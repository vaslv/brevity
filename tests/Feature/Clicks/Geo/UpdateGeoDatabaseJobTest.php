<?php

namespace Tests\Feature\Clicks\Geo;

use App\Jobs\UpdateGeoDatabaseJob;
use Tests\TestCase;

/**
 * Stage 4 (review 2026-07-13, r22): the geo download does an HTTP fetch of a
 * ~50 MB archive (GeoDatabaseDownloader uses Http::timeout(120)) plus
 * extraction, which can outlast supervisor-1's 60s worker timeout. The job pins
 * its own timeout so the worker is not SIGKILLed mid-run — a kill skips the
 * finally that releases the download lock (wedged for its 1800s TTL) and never
 * runs recordOutcome, so the failure backoff never engages.
 */
class UpdateGeoDatabaseJobTest extends TestCase
{
    public function test_it_pins_a_timeout_between_the_http_fetch_and_its_supervisor(): void
    {
        $timeout = (new UpdateGeoDatabaseJob)->timeout;
        $supervisorTimeout = (int) config('horizon.defaults.supervisor-geo.timeout');

        // Above the 120s HTTP timeout so a slow-but-progressing download runs to
        // completion, and strictly below its own supervisor's worker timeout so
        // the worker alarm (not a force-kill) bounds it and a deploy-time
        // shutdown grace lets an in-flight download finish.
        $this->assertGreaterThan(120, $timeout);
        $this->assertLessThan($supervisorTimeout, $timeout);
    }

    public function test_it_runs_on_the_dedicated_geo_queue(): void
    {
        // Keeps the long-running download off the click-recording pool.
        $this->assertSame('geo', (new UpdateGeoDatabaseJob)->queue);
    }
}
