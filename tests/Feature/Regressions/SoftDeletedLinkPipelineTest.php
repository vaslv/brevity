<?php

namespace Tests\Feature\Regressions;

use App\Jobs\RecordClickJob;
use App\Jobs\SendCallbackJob;
use App\Models\Callback;
use App\Models\Click;
use App\Models\Link;
use App\Models\Service;
use App\Models\Url;
use App\Services\Links\Callbacks\CallbackStatus;
use App\Services\Links\Callbacks\CallbackUrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression for docs/08-decisions.md (audit 2026-06) — M2 and M3.
 *
 * The async pipeline resolved the link with the SoftDeletes scope applied:
 * RecordClickJob's Link::findOrFail threw (dropping a real click) and
 * SendCallbackJob's click->link->service null-dereferenced (crashing the
 * callback) once a link was soft-deleted between dispatch and execution.
 * Clicks/callbacks are historical facts that must outlive the link, so the
 * pipeline now resolves trashed links and records/delivers as normal.
 */
class SoftDeletedLinkPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_callback_for_a_soft_deleted_link_is_still_delivered(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        // 93.184.216.34 is a public IP, so the SSRF guard allows it.
        $service = Service::query()->create([
            'name' => 'Svc '.fake()->unique()->word(),
            'callback_url' => 'https://93.184.216.34/hook',
        ]);
        $link = Link::query()->create([
            'service_id' => $service->id,
            'forward_query' => false,
            'callback_data' => ['x' => 'y'],
        ]);
        $url = Url::query()->create(['value' => 'https://example.com/landing']);
        $click = Click::query()->create([
            'uuid' => (string) Str::uuid(),
            'service_id' => $service->id,
            'link_id' => $link->id,
            'url_id' => $url->id,
        ]);
        $callback = Callback::query()->create([
            'service_id' => $service->id,
            'click_id' => $click->id,
            'data' => ['x' => 'y'],
            'status' => CallbackStatus::Pending,
            'attempts' => 0,
        ]);

        $link->delete();

        (new SendCallbackJob($callback->id))->handle(app(CallbackUrlGuard::class));

        Http::assertSentCount(1);
        $this->assertSame(CallbackStatus::Sent, $callback->refresh()->status);
    }

    public function test_a_click_for_a_soft_deleted_link_is_still_recorded(): void
    {
        $service = Service::query()->create(['name' => 'Svc '.fake()->unique()->word()]);
        $link = Link::query()->create(['service_id' => $service->id, 'forward_query' => false]);
        $url = Url::query()->create(['value' => 'https://example.com/landing']);

        // The link is soft-deleted after the resolve already dispatched the job.
        $link->delete();

        RecordClickJob::dispatchSync(
            (string) Str::uuid(),
            $link->id,
            $url->id,
            '198.51.100.7',
            null,
            'UA/1.0',
        );

        $this->assertDatabaseHas('clicks', ['link_id' => $link->id, 'url_id' => $url->id]);
    }
}
