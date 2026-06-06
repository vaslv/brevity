<?php

namespace Tests\Feature\Regressions;

use App\Providers\AppServiceProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Regression for docs/AUDIT_2026-06.md — M9.
 *
 * config/queue.php defaulted to the 'database' connection while Horizon only
 * drains 'redis', so a forgotten/misconfigured QUEUE_CONNECTION silently routed
 * clicks/callbacks to an undrained queue. The default is now 'redis' and a
 * production boot guard fails fast on any other connection.
 */
class QueueConnectionGuardTest extends TestCase
{
    public function test_production_boot_passes_with_redis(): void
    {
        $this->app['env'] = 'production';
        config(['queue.default' => 'redis']);

        try {
            (new AppServiceProvider($this->app))->boot();

            $this->assertSame('redis', config('queue.default'));
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_production_boot_rejects_a_non_redis_queue(): void
    {
        $this->app['env'] = 'production';
        config(['queue.default' => 'database']);

        try {
            $this->expectException(RuntimeException::class);

            (new AppServiceProvider($this->app))->boot();
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_queue_default_falls_back_to_redis(): void
    {
        $source = file_get_contents(config_path('queue.php'));

        $this->assertStringContainsString("env('QUEUE_CONNECTION', 'redis')", $source);
    }
}
