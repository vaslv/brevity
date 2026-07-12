<?php

namespace Tests\Feature\Links;

use App\Models\Callback;
use App\Models\Click;
use App\Models\Link;
use App\Models\LinkClickCounter;
use App\Models\Rule;
use App\Models\Service;
use App\Models\Url;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stage 2 of docs/07-plans.md — link lifecycle limits on resolve.
 *
 * A link is alive only while valid_since is not in the future, valid_until is
 * not in the past, and the counter sum (ALL clicks, bots included — decision
 * 2026-07-12) stays below max_clicks. A dead link is indistinguishable from a
 * missing one: 404, no click recorded, no callback sent. NULL means no limit.
 */
class LinkLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_dead_link_sends_no_callback(): void
    {
        $service = Service::factory()->create([
            'callback_url' => 'https://93.184.216.34/hook',
        ]);
        $code = $this->setupLink(Link::factory()->expired()->state([
            'service_id' => $service->id,
            'callback_data' => ['x' => '{{click.id}}'],
        ]));

        $this->get(static::SHORT_LINK_HOST.'/'.$code)->assertNotFound();

        $this->assertSame(0, Click::query()->count());
        $this->assertSame(0, Callback::query()->count());
    }

    public function test_a_link_at_its_click_limit_is_404(): void
    {
        $code = $this->setupLink(Link::factory()->withMaxClicks(5));
        $link = Link::query()->where('code', $code)->firstOrFail();

        // Bot clicks count toward the limit too (all clicks, decision 2026-07-12).
        LinkClickCounter::query()->create(['link_id' => $link->id, 'is_bot' => false, 'slot' => 1, 'count' => 3]);
        LinkClickCounter::query()->create(['link_id' => $link->id, 'is_bot' => true, 'slot' => 2, 'count' => 2]);

        $this->get(static::SHORT_LINK_HOST.'/'.$code)->assertNotFound();

        $this->assertSame(0, Click::query()->count());
    }

    public function test_a_link_before_valid_since_is_404_and_records_nothing(): void
    {
        $code = $this->setupLink(Link::factory()->scheduled());

        $this->get(static::SHORT_LINK_HOST.'/'.$code)->assertNotFound();

        $this->assertSame(0, Click::query()->count());
    }

    public function test_a_link_below_its_click_limit_resolves(): void
    {
        $code = $this->setupLink(Link::factory()->withMaxClicks(5));
        $link = Link::query()->where('code', $code)->firstOrFail();

        LinkClickCounter::query()->create(['link_id' => $link->id, 'is_bot' => false, 'slot' => 1, 'count' => 4]);

        $this->get(static::SHORT_LINK_HOST.'/'.$code)->assertRedirect();
    }

    public function test_a_link_inside_its_window_resolves(): void
    {
        $code = $this->setupLink(Link::factory()->state([
            'valid_since' => now()->subHour(),
            'valid_until' => now()->addHour(),
        ]));

        $this->get(static::SHORT_LINK_HOST.'/'.$code)->assertRedirect();

        $this->assertSame(1, Click::query()->count());
    }

    public function test_a_link_past_valid_until_is_404(): void
    {
        $code = $this->setupLink(Link::factory()->expired());

        $this->get(static::SHORT_LINK_HOST.'/'.$code)->assertNotFound();

        $this->assertSame(0, Click::query()->count());
    }

    public function test_a_link_without_limits_ignores_counters(): void
    {
        $code = $this->setupLink(Link::factory());
        $link = Link::query()->where('code', $code)->firstOrFail();

        LinkClickCounter::query()->create(['link_id' => $link->id, 'is_bot' => false, 'slot' => 1, 'count' => 1000]);

        $this->get(static::SHORT_LINK_HOST.'/'.$code)->assertRedirect();
    }

    public function test_boundary_equality_counts_as_alive(): void
    {
        // Freeze time on a whole second: the DB column drops microseconds, so
        // an equality check only holds at second precision. valid_since == now
        // and valid_until == now are both inclusive — the window edges belong
        // to the "alive" side.
        $this->travelTo(now()->startOfSecond());

        $code = $this->setupLink(Link::factory()->state([
            'valid_since' => now(),
            'valid_until' => now(),
        ]));

        $this->get(static::SHORT_LINK_HOST.'/'.$code)->assertRedirect();
    }

    /**
     * @param  Factory<Link>  $factory
     */
    private function setupLink($factory): string
    {
        $link = $factory->create();

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
