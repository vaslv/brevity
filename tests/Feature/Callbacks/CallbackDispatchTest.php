<?php

namespace Tests\Feature\Callbacks;

use App\Jobs\SendCallbackJob;
use App\Models\Callback;
use App\Models\Link;
use App\Models\Rule;
use App\Models\Service;
use App\Models\Url;
use App\Services\Links\Callbacks\CallbackStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CallbackDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_click_recording_dispatches_send_callback_job(): void
    {
        Queue::fake([SendCallbackJob::class]);

        $code = $this->setupLinkWithCallback(
            callbackUrl: 'https://93.184.216.34/hook',
            callbackData: ['x' => '{{click.id}}'],
        );

        $this->get(static::SHORT_LINK_HOST.'/'.$code)->assertRedirect();

        Queue::assertPushed(SendCallbackJob::class, 1);
    }

    public function test_it_blocks_unsafe_callback_url_without_making_http_call(): void
    {
        Http::fake();

        $code = $this->setupLinkWithCallback(
            callbackUrl: 'http://127.0.0.1/hook',
            callbackData: ['x' => '{{click.id}}'],
        );

        $this->get(static::SHORT_LINK_HOST.'/'.$code)->assertRedirect();

        $callback = Callback::query()->firstOrFail();

        $this->assertSame(CallbackStatus::Failed, $callback->status);
        $this->assertSame(0, $callback->attempts);
        Http::assertNothingSent();
    }

    public function test_it_does_not_create_callback_when_link_has_no_callback_data(): void
    {
        Http::fake();

        $code = $this->setupLinkWithCallback(
            callbackUrl: 'https://93.184.216.34/hook',
            callbackData: null,
        );

        $this->get(static::SHORT_LINK_HOST.'/'.$code)->assertRedirect();

        $this->assertSame(0, Callback::query()->count());
        Http::assertNothingSent();
    }

    public function test_it_does_not_create_callback_when_service_has_no_callback_url(): void
    {
        Http::fake();

        $code = $this->setupLinkWithCallback(
            callbackUrl: null,
            callbackData: ['x' => '{{click.id}}'],
        );

        $this->get(static::SHORT_LINK_HOST.'/'.$code)->assertRedirect();

        $this->assertSame(0, Callback::query()->count());
        Http::assertNothingSent();
    }

    public function test_it_marks_callback_failed_immediately_on_4xx_response(): void
    {
        Http::fake([
            'https://93.184.216.34/hook' => Http::response(['bad' => 'request'], 400),
        ]);

        $code = $this->setupLinkWithCallback(
            callbackUrl: 'https://93.184.216.34/hook',
            callbackData: ['x' => '{{click.id}}'],
        );

        $this->get(static::SHORT_LINK_HOST.'/'.$code)->assertRedirect();

        $callback = Callback::query()->firstOrFail();

        $this->assertSame(CallbackStatus::Failed, $callback->status);
        $this->assertSame(400, $callback->response_code);
        // 4xx is permanent: exactly one attempt.
        $this->assertSame(1, $callback->attempts);
        // Only one HTTP call should have been made.
        Http::assertSentCount(1);
    }

    public function test_it_sanitizes_invalid_utf8_and_nul_bytes_in_response_body(): void
    {
        $invalidBody = "ok\xC3\x28 \x00 tail";

        Http::fake([
            'https://93.184.216.34/hook' => Http::response($invalidBody, 200),
        ]);

        $code = $this->setupLinkWithCallback(
            callbackUrl: 'https://93.184.216.34/hook',
            callbackData: ['x' => '{{click.id}}'],
        );

        $this->get(static::SHORT_LINK_HOST.'/'.$code)->assertRedirect();

        $callback = Callback::query()->firstOrFail();

        $this->assertSame(CallbackStatus::Sent, $callback->status);
        $this->assertStringNotContainsString("\x00", $callback->response_body);
        $this->assertTrue(mb_check_encoding($callback->response_body, 'UTF-8'));
    }

    public function test_it_sends_callback_with_rendered_template_on_successful_response(): void
    {
        Http::fake([
            'https://93.184.216.34/hook' => Http::response(['ok' => true], 200),
        ]);

        $code = $this->setupLinkWithCallback(
            callbackUrl: 'https://93.184.216.34/hook',
            callbackData: ['click_id' => '{{click.id}}', 'code' => '{{link.code}}'],
        );

        $this->get(static::SHORT_LINK_HOST.'/'.$code)->assertRedirect();

        $callback = Callback::query()->firstOrFail();

        $this->assertSame(CallbackStatus::Sent, $callback->status);
        $this->assertSame(200, $callback->response_code);
        $this->assertSame(1, $callback->attempts);
        $this->assertSame($code, $callback->data['code']);
        $this->assertNotEmpty($callback->data['click_id']);

        Http::assertSent(fn ($request) => $request->url() === 'https://93.184.216.34/hook'
            && $request['code'] === $code);
    }

    public function test_it_treats_a_redirect_response_as_a_permanent_failure_without_following(): void
    {
        // SSRF hardening (review 2026-06 M3): redirects are not followed
        // (allow_redirects => false), and a 3xx is a permanent, non-retryable
        // failure — a callback endpoint must never bounce us to another host
        // (e.g. 169.254.169.254) the send-time guard never validated.
        Http::fake([
            'https://93.184.216.34/hook' => Http::response('', 302, [
                'Location' => 'http://169.254.169.254/latest/meta-data/',
            ]),
        ]);

        $code = $this->setupLinkWithCallback(
            callbackUrl: 'https://93.184.216.34/hook',
            callbackData: ['x' => '{{click.id}}'],
        );

        $this->get(static::SHORT_LINK_HOST.'/'.$code)->assertRedirect();

        $callback = Callback::query()->firstOrFail();

        $this->assertSame(CallbackStatus::Failed, $callback->status);
        $this->assertSame(302, $callback->response_code);
        // Permanent: exactly one attempt, no follow to the redirect target.
        $this->assertSame(1, $callback->attempts);
        Http::assertSentCount(1);
    }

    private function setupLinkWithCallback(?string $callbackUrl, ?array $callbackData): string
    {
        $service = Service::query()->create([
            'name' => 'Callback Service '.fake()->unique()->word(),
            'callback_url' => $callbackUrl,
        ]);

        $link = Link::query()->create([
            'service_id' => $service->id,
            'title' => 'Callback test',
            'forward_query' => false,
            'callback_data' => $callbackData,
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
