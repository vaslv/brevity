<?php

namespace Tests\Feature\Regressions;

use App\Jobs\RecordClickJob;
use App\Models\Click;
use App\Models\Link;
use App\Models\Rule;
use App\Models\Service;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Regression for docs/07-plans.md — r43.
 *
 * clicks.created_at used to be stamped when the async RecordClickJob ran, so a
 * queue backlog made a click's recorded time lag the real visit by minutes to
 * hours, skewing time-based analytics and the ips:prune cutoff. The visit
 * instant is now captured at redirect time and carried through the job. We
 * capture the dispatched job at visit time and run it three hours later to model
 * the backlog (capturing the instance keeps this independent of the signature).
 */
class ClickVisitTimestampTest extends TestCase
{
    use RefreshDatabase;

    public function test_click_created_at_is_the_visit_instant_not_the_job_run_time(): void
    {
        Queue::fake([RecordClickJob::class]);

        $code = $this->setupLink();

        $visitedAt = now()->startOfSecond();
        $this->travelTo($visitedAt);
        $this->get(static::SHORT_LINK_HOST.'/'.$code)->assertRedirect();

        $captured = null;
        Queue::assertPushed(RecordClickJob::class, function (RecordClickJob $job) use (&$captured): bool {
            $captured = $job;

            return true;
        });

        // The job drains from the backlog three hours after the visit.
        $this->travelTo($visitedAt->copy()->addHours(3));
        app()->call([$captured, 'handle']);

        $click = Click::query()->firstOrFail();
        $this->assertEqualsWithDelta($visitedAt->timestamp, $click->created_at->timestamp, 1);
    }

    private function setupLink(): string
    {
        $service = Service::query()->create(['name' => 'Svc '.fake()->unique()->word()]);
        $link = Link::query()->create(['service_id' => $service->id, 'forward_query' => false]);

        $code = fake()->unique()->bothify('????####');
        $link->update(['code' => $code]);

        $url = Url::query()->create(['value' => 'https://target.example/dest']);
        Rule::query()->create(['link_id' => $link->id, 'url_id' => $url->id, 'priority' => 1]);

        return $code;
    }
}
