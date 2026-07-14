<?php

namespace Tests\Feature\Regressions;

use Tests\TestCase;

/**
 * Regression for docs/07-plans.md — r42.
 *
 * RecordClickJob runs on the 'default' queue and resolves geolocation there;
 * MaxMindGeoLocator caches the open MaxMind Reader for the worker's whole life.
 * If those workers never recycle, a freshly installed .mmdb (dropped in by the
 * traffic-triggered UpdateGeoDatabaseJob) is only adopted on the next deploy.
 * The click supervisor must therefore recycle its workers on a bounded schedule.
 */
class GeoDatabaseWorkerRecycleTest extends TestCase
{
    public function test_click_supervisor_recycles_workers_on_a_bounded_schedule(): void
    {
        $supervisors = collect(config('horizon.defaults'));

        $clicks = $supervisors->first(
            fn (array $supervisor): bool => in_array('default', $supervisor['queue'] ?? [], true),
        );

        $this->assertNotNull($clicks, 'no supervisor processes the default queue');

        $recycles = ($clicks['maxTime'] ?? 0) > 0 || ($clicks['maxJobs'] ?? 0) > 0;
        $this->assertTrue(
            $recycles,
            'the click supervisor must recycle workers (maxTime or maxJobs > 0) so a new geo database is adopted without a deploy',
        );
    }
}
