<?php

namespace Tests\Feature\Clicks;

use App\Models\Callback;
use App\Models\Click;
use App\Models\Link;
use App\Models\Rule;
use App\Models\Service;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Stage 2 of docs/07-plans.md — traffic hygiene (TRK-04).
 *
 * Visits from configured ignored sources (exact IP / CIDR) redirect normally
 * but record no click and send no callback; the configured disable-param does
 * the same for a single visit and never leaks into the forwarded query.
 */
class TrafficHygieneTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_cidr_ignored_range_records_nothing(): void
    {
        config()->set('tracking.ignored_sources', '10.0.0.0/8, 203.0.113.0/24');

        $code = $this->setupLink();

        $this->call('GET', static::SHORT_LINK_HOST.'/'.$code, server: ['REMOTE_ADDR' => '203.0.113.5'])
            ->assertRedirect();

        $this->assertSame(0, Click::query()->count());
    }

    public function test_a_garbage_config_entry_never_matches_everything(): void
    {
        // Symfony coerces "/abc" to /0 — a raw pass-through would silently
        // ignore ALL traffic. Invalid entries must be skipped; valid ones
        // must keep working.
        config()->set('tracking.ignored_sources', '10.0.0.0/abc, office, 203.0.113.77');

        $code = $this->setupLink();

        $this->call('GET', static::SHORT_LINK_HOST.'/'.$code, server: ['REMOTE_ADDR' => '198.51.100.1'])
            ->assertRedirect();
        $this->assertSame(1, Click::query()->count());

        $this->call('GET', static::SHORT_LINK_HOST.'/'.$this->setupLink(), server: ['REMOTE_ADDR' => '203.0.113.77'])
            ->assertRedirect();
        $this->assertSame(1, Click::query()->count());
    }

    public function test_a_non_matching_ip_is_tracked(): void
    {
        config()->set('tracking.ignored_sources', '203.0.113.0/24');

        $code = $this->setupLink();

        $this->call('GET', static::SHORT_LINK_HOST.'/'.$code, server: ['REMOTE_ADDR' => '198.51.100.1'])
            ->assertRedirect();

        $this->assertSame(1, Click::query()->count());
    }

    public function test_an_exactly_ignored_ip_records_nothing(): void
    {
        config()->set('tracking.ignored_sources', '203.0.113.77');

        $code = $this->setupLink();

        // The test client's REMOTE_ADDR is 127.0.0.1 by default; simulate the
        // ignored source explicitly.
        $this->call('GET', static::SHORT_LINK_HOST.'/'.$code, server: ['REMOTE_ADDR' => '203.0.113.77'])
            ->assertRedirect();

        $this->assertSame(0, Click::query()->count());
        $this->assertSame(0, Callback::query()->count());
    }

    public function test_an_ipv6_cidr_is_matched(): void
    {
        config()->set('tracking.ignored_sources', '2001:db8::/32');

        $code = $this->setupLink();

        $this->call('GET', static::SHORT_LINK_HOST.'/'.$code, server: ['REMOTE_ADDR' => '2001:db8::17'])
            ->assertRedirect();

        $this->assertSame(0, Click::query()->count());
    }

    public function test_everything_is_tracked_with_empty_config(): void
    {
        config()->set('tracking.ignored_sources', '');
        config()->set('tracking.disable_param', '');

        $code = $this->setupLink();

        $this->get(static::SHORT_LINK_HOST.'/'.$code)->assertRedirect();

        $this->assertSame(1, Click::query()->count());
    }

    public function test_the_disable_param_is_stripped_from_the_forwarded_query(): void
    {
        config()->set('tracking.disable_param', 'notrack');

        $code = $this->setupLink(forwardQuery: true);

        $response = $this->get(static::SHORT_LINK_HOST.'/'.$code.'?notrack=1&utm_source=tg');

        $location = $response->headers->get('Location');
        $this->assertStringContainsString('utm_source=tg', (string) $location);
        $this->assertStringNotContainsString('notrack', (string) $location);
    }

    public function test_the_disable_param_skips_tracking(): void
    {
        config()->set('tracking.disable_param', 'notrack');

        $code = $this->setupLink();

        $this->get(static::SHORT_LINK_HOST.'/'.$code.'?notrack')
            ->assertRedirect();

        $this->assertSame(0, Click::query()->count());
    }

    private function setupLink(bool $forwardQuery = false): string
    {
        Http::fake();

        $service = Service::query()->create([
            'name' => 'Hygiene Service '.fake()->unique()->word(),
            'callback_url' => 'https://93.184.216.34/hook',
        ]);

        $link = Link::factory()->create([
            'service_id' => $service->id,
            'forward_query' => $forwardQuery,
            'callback_data' => ['x' => '{{click.id}}'],
        ]);

        $code = fake()->unique()->bothify('????####');
        $link->update(['code' => $code]);

        Rule::factory()->create([
            'link_id' => $link->id,
            'url_id' => Url::factory()->create()->id,
            'priority' => 1,
        ]);

        return $code;
    }
}
