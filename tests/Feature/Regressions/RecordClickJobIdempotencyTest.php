<?php

namespace Tests\Feature\Regressions;

use App\Jobs\RecordClickJob;
use App\Jobs\SendCallbackJob;
use App\Models\Callback;
use App\Models\Click;
use App\Models\Link;
use App\Models\Rule;
use App\Models\Service;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Regression for docs/08-decisions.md (review 2026-06) — M2 (Major).
 *
 * `RecordClickJob` records a Click and creates a Callback on every execution
 * with no idempotency key. Queue delivery is at-least-once: if the job is
 * retried (e.g. a Redis blip while dispatching `SendCallbackJob`, after the
 * Click row was already committed) it duplicates both the Click and the
 * outbound Callback/webhook.
 *
 * We capture the exact job instance the controller dispatched and run
 * `handle()` twice to model that retry (capturing the instance keeps this test
 * independent of the job's constructor signature).
 *
 * Fixed: a per-resolve `clicks.uuid` idempotency key (`ClickRecorder` uses
 * `firstOrCreate` on it) plus `firstOrCreate` on `callbacks.click_id` with a
 * `wasRecentlyCreated` dispatch guard. This test guards that re-execution stays
 * idempotent.
 */
class RecordClickJobIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_retried_job_does_not_duplicate_click_or_callback(): void
    {
        Queue::fake([RecordClickJob::class, SendCallbackJob::class]);

        $code = $this->setupLinkWithCallback();

        $this->get(static::SHORT_LINK_HOST.'/'.$code)->assertRedirect();

        $captured = null;
        Queue::assertPushed(RecordClickJob::class, function (RecordClickJob $job) use (&$captured): bool {
            $captured = $job;

            return true;
        });

        // Run the job, then run it again — an at-least-once retry of the same job.
        app()->call([$captured, 'handle']);
        $dataAfterFirstRun = Callback::query()->firstOrFail()->data;
        app()->call([$captured, 'handle']);

        $this->assertSame(1, Click::query()->count(), 'A retried RecordClickJob must not create a second Click.');
        $this->assertSame(1, Callback::query()->count(), 'A retried RecordClickJob must not create a second Callback.');
        Queue::assertPushed(SendCallbackJob::class, 1);

        // The stored payload (including the server-set is_bot mark) must
        // survive the retry unchanged: firstOrCreate never rewrites `data`.
        $callback = Callback::query()->firstOrFail();
        $this->assertSame($dataAfterFirstRun, $callback->data);
        $this->assertArrayHasKey('is_bot', $callback->data);
    }

    private function setupLinkWithCallback(): string
    {
        $service = Service::query()->create([
            'name' => 'Idempotency Service '.fake()->unique()->word(),
            'callback_url' => 'https://93.184.216.34/hook',
        ]);

        $link = Link::query()->create([
            'service_id' => $service->id,
            'title' => 'Idempotency test',
            'forward_query' => false,
            'callback_data' => ['click_id' => '{{click.id}}'],
        ]);

        $code = fake()->unique()->bothify('????####');
        $link->update(['code' => $code]);

        $url = Url::query()->create(['value' => 'https://target.example/dest']);

        Rule::query()->create([
            'link_id' => $link->id,
            'url_id' => $url->id,
            'priority' => 1,
        ]);

        return $code;
    }
}
