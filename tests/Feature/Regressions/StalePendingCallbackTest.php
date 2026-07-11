<?php

namespace Tests\Feature\Regressions;

use App\Jobs\SendCallbackJob;
use App\Models\Callback;
use App\Models\Click;
use App\Models\Link;
use App\Models\Service;
use App\Models\Url;
use App\Services\Links\Callbacks\CallbackStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression for docs/08-decisions.md (audit 2026-06) — M4.
 *
 * CallbackDispatcher only enqueues SendCallbackJob on wasRecentlyCreated, so a
 * crash between the Callback row commit and the Redis enqueue left the row stuck
 * at status=Pending forever with no retry. The callbacks:redispatch-stale command
 * re-enqueues Pending callbacks that have been idle beyond the retry window.
 */
class StalePendingCallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_pending_callback_is_not_redispatched(): void
    {
        Queue::fake([SendCallbackJob::class]);

        // Within the retry window: its job may still be in flight / in backoff.
        $this->makeCallback(CallbackStatus::Pending, now()->subMinutes(30));

        $this->artisan('callbacks:redispatch-stale')->assertSuccessful();

        Queue::assertNotPushed(SendCallbackJob::class);
    }

    public function test_a_stale_pending_callback_is_redispatched(): void
    {
        Queue::fake([SendCallbackJob::class]);

        $this->makeCallback(CallbackStatus::Pending, now()->subHours(3));
        // A Sent callback (also old) must be ignored — only Pending is retried.
        $this->makeCallback(CallbackStatus::Sent, now()->subHours(3));

        $this->artisan('callbacks:redispatch-stale')->assertSuccessful();

        Queue::assertPushed(SendCallbackJob::class, 1);
    }

    private function makeCallback(CallbackStatus $status, Carbon $createdAt): Callback
    {
        $service = Service::query()->create(['name' => 'Svc '.fake()->unique()->word()]);
        $link = Link::query()->create(['service_id' => $service->id, 'forward_query' => false]);
        $url = Url::query()->create(['value' => 'https://example.com/'.fake()->unique()->slug()]);
        $click = Click::query()->create([
            'uuid' => (string) Str::uuid(),
            'service_id' => $service->id,
            'link_id' => $link->id,
            'url_id' => $url->id,
        ]);

        $callback = Callback::query()->create([
            'service_id' => $service->id,
            'click_id' => $click->id,
            'data' => [],
            'status' => $status,
            'attempts' => 0,
        ]);

        // created_at is not fillable; set it directly to simulate age.
        $callback->created_at = $createdAt;
        $callback->save();

        return $callback;
    }
}
