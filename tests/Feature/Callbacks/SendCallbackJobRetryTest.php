<?php

namespace Tests\Feature\Callbacks;

use App\Jobs\SendCallbackJob;
use App\Models\Callback;
use App\Models\Click;
use App\Models\Link;
use App\Models\Service;
use App\Models\Url;
use App\Services\Links\Callbacks\CallbackStatus;
use App\Services\Links\Callbacks\CallbackUrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Retry/exhaustion coverage for outbound callbacks (docs/AUDIT_2026-06.md —
 * Phase 4). 4xx/3xx permanent failures are covered by CallbackDispatchTest;
 * this pins the retryable 5xx path and the terminal failed() hook.
 */
class SendCallbackJobRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_5xx_response_keeps_the_callback_pending_and_throws_for_retry(): void
    {
        Http::fake(['https://93.184.216.34/hook' => Http::response('upstream error', 500)]);

        $callback = $this->pendingCallback();

        try {
            (new SendCallbackJob($callback->id))->handle(app(CallbackUrlGuard::class));
            $this->fail('Expected a RuntimeException so the queue retries the job.');
        } catch (RuntimeException) {
            // Expected: throwing (rather than fail()) lets the queue retry with backoff.
        }

        $callback->refresh();

        $this->assertSame(CallbackStatus::Pending, $callback->status);
        $this->assertSame(500, $callback->response_code);
        $this->assertSame(1, $callback->attempts);
        Http::assertSentCount(1);
    }

    public function test_the_failed_hook_marks_the_callback_failed_when_retries_are_exhausted(): void
    {
        Exceptions::fake();

        $callback = $this->pendingCallback();

        // Laravel invokes failed() after the final retry throws; call it directly.
        (new SendCallbackJob($callback->id))->failed(new RuntimeException('retries exhausted'));

        $this->assertSame(CallbackStatus::Failed, $callback->refresh()->status);
    }

    private function pendingCallback(): Callback
    {
        $service = Service::factory()->withCallbackUrl('https://93.184.216.34/hook')->create();
        $link = Link::factory()->create(['service_id' => $service->id]);
        $url = Url::factory()->create();
        $click = Click::factory()->create([
            'service_id' => $service->id,
            'link_id' => $link->id,
            'url_id' => $url->id,
        ]);

        return Callback::factory()->create([
            'service_id' => $service->id,
            'click_id' => $click->id,
            'data' => ['x' => 'y'],
        ]);
    }
}
