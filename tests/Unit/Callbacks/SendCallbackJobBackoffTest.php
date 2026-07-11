<?php

namespace Tests\Unit\Callbacks;

use App\Jobs\SendCallbackJob;
use PHPUnit\Framework\TestCase;

/**
 * Guard for docs/08-decisions.md (review 2026-06) — m11: the backoff schedule has exactly
 * `tries - 1` gaps (no dead trailing element).
 */
class SendCallbackJobBackoffTest extends TestCase
{
    public function test_backoff_has_one_gap_per_retry(): void
    {
        $job = new SendCallbackJob(1);

        $this->assertSame(5, $job->tries);
        $this->assertSame([60, 300, 900, 3600], $job->backoff());
        $this->assertCount($job->tries - 1, $job->backoff());
    }
}
