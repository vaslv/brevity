<?php

namespace Tests\Feature\Callbacks;

use App\Models\Callback;
use App\Models\Link;
use App\Models\Rule;
use App\Models\Service;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Stage 1 of docs/07-plans.md — the is_bot mark in callbacks
 * (docs/03-api.md §10, «Метка бота»).
 *
 * Every callback payload carries a root-level boolean `is_bot`, regardless of
 * the client template; a client-supplied `is_bot` key is overridden (reserved).
 * Bot clicks still produce callbacks — the partner decides what to count.
 */
class CallbackBotFlagTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    private const CRAWLER_UA = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

    public function test_a_visit_without_user_agent_is_not_a_bot(): void
    {
        Http::fake();

        $code = $this->setupLinkWithCallback(['x' => '{{click.id}}']);

        // Symfony's test client sends its own default User-Agent unless the
        // server value is cleared explicitly.
        $this->call('GET', static::SHORT_LINK_HOST.'/'.$code, server: ['HTTP_USER_AGENT' => ''])
            ->assertRedirect();

        $data = Callback::query()->firstOrFail()->data;

        $this->assertFalse($data['is_bot']);
    }

    public function test_client_supplied_is_bot_key_is_overridden(): void
    {
        Http::fake();

        $code = $this->setupLinkWithCallback(['is_bot' => 'client-value']);

        $this->get(static::SHORT_LINK_HOST.'/'.$code, ['User-Agent' => self::BROWSER_UA])
            ->assertRedirect();

        $data = Callback::query()->firstOrFail()->data;

        // The root key is reserved: the server value wins over the template's.
        $this->assertFalse($data['is_bot']);
    }

    public function test_is_bot_placeholder_renders_as_string_inside_values(): void
    {
        Http::fake();

        $code = $this->setupLinkWithCallback(['bot' => '{{click.is_bot}}']);

        $this->get(static::SHORT_LINK_HOST.'/'.$code, ['User-Agent' => self::CRAWLER_UA])
            ->assertRedirect();

        $data = Callback::query()->firstOrFail()->data;

        $this->assertSame('true', $data['bot']);
    }

    public function test_payload_root_carries_is_bot_false_for_a_browser_visit(): void
    {
        Http::fake();

        $code = $this->setupLinkWithCallback(['x' => '{{click.id}}']);

        $this->get(static::SHORT_LINK_HOST.'/'.$code, ['User-Agent' => self::BROWSER_UA])
            ->assertRedirect();

        $data = Callback::query()->firstOrFail()->data;

        $this->assertFalse($data['is_bot']);
    }

    public function test_payload_root_carries_is_bot_true_for_a_crawler_visit(): void
    {
        Http::fake();

        $code = $this->setupLinkWithCallback(['x' => '{{click.id}}']);

        $this->get(static::SHORT_LINK_HOST.'/'.$code, ['User-Agent' => self::CRAWLER_UA])
            ->assertRedirect();

        $data = Callback::query()->firstOrFail()->data;

        $this->assertTrue($data['is_bot']);
    }

    private function setupLinkWithCallback(array $callbackData): string
    {
        $service = Service::factory()->create([
            'callback_url' => 'https://93.184.216.34/hook',
        ]);

        $link = Link::factory()->create([
            'service_id' => $service->id,
            'callback_data' => $callbackData,
        ]);

        $code = fake()->unique()->bothify('????####');
        $link->update(['code' => $code]);

        $url = Url::factory()->create();

        Rule::factory()->create([
            'link_id' => $link->id,
            'url_id' => $url->id,
            'priority' => 1,
        ]);

        return $code;
    }
}
