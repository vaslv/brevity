<?php

namespace Tests\Feature\Regressions;

use App\Jobs\SendCallbackJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Regression for docs/AUDIT_2026-06.md — M13.
 *
 * Click recording and callback delivery shared the single 'default' queue, so a
 * callback backlog (slow endpoints, up to ~1h backoff) could starve click
 * recording. Callbacks now run on a dedicated 'callbacks' queue with its own
 * Horizon supervisor.
 */
class CallbackQueueIsolationTest extends TestCase
{
    public function test_callback_delivery_runs_on_a_dedicated_queue(): void
    {
        Queue::fake();

        SendCallbackJob::dispatch(1);

        Queue::assertPushedOn('callbacks', SendCallbackJob::class);
    }

    public function test_horizon_isolates_the_callbacks_queue_in_its_own_supervisor(): void
    {
        $supervisors = collect(config('horizon.defaults'));

        $clicks = $supervisors->first(fn (array $s): bool => in_array('default', $s['queue'] ?? [], true));
        $callbacks = $supervisors->first(fn (array $s): bool => in_array('callbacks', $s['queue'] ?? [], true));

        $this->assertNotNull($clicks, 'no supervisor processes the default queue');
        $this->assertNotNull($callbacks, 'no supervisor processes the callbacks queue');
        $this->assertNotSame($clicks, $callbacks, 'clicks and callbacks must not share a supervisor');
    }
}
