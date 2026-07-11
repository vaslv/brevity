<?php

namespace Tests\Feature\Clicks;

use App\Models\Link;
use App\Models\LinkClickCounter;
use App\Models\Url;
use App\Services\Links\Clicks\ClickRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Stage 1 of docs/07-plans.md — slotted pre-aggregated click counters.
 *
 * Every recorded click increments a (link, is_bot, random slot) counter in the
 * same transaction as the click insert; a retried RecordClickJob (same uuid)
 * must not increment twice. A link's total is the SUM over its slots.
 */
class ClickCounterTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    private const CRAWLER_UA = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

    public function test_a_bot_click_increments_the_bot_counter(): void
    {
        $link = Link::factory()->create();

        $this->recordClick($link, self::CRAWLER_UA);

        $this->assertSame(1, $this->counterSum($link, isBot: true));
        $this->assertSame(0, $this->counterSum($link, isBot: false));
    }

    public function test_a_click_without_user_agent_counts_as_non_bot(): void
    {
        $link = Link::factory()->create();

        $this->recordClick($link, null);

        $this->assertSame(1, $this->counterSum($link, isBot: false));
    }

    public function test_a_recorded_click_increments_the_non_bot_counter(): void
    {
        $link = Link::factory()->create();

        $this->recordClick($link, self::BROWSER_UA);

        $this->assertSame(1, $this->counterSum($link, isBot: false));
        $this->assertSame(0, $this->counterSum($link, isBot: true));
    }

    public function test_a_retry_with_the_same_uuid_does_not_double_count(): void
    {
        $link = Link::factory()->create();
        $uuid = (string) Str::uuid();

        $this->recordClick($link, self::BROWSER_UA, $uuid);
        $this->recordClick($link, self::BROWSER_UA, $uuid);

        $this->assertSame(1, $this->counterSum($link, isBot: false));
    }

    public function test_the_sum_over_slots_matches_the_number_of_clicks(): void
    {
        $link = Link::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->recordClick($link, self::BROWSER_UA);
        }

        $this->assertSame(10, $this->counterSum($link, isBot: false));

        // Slots stay within the declared range regardless of distribution.
        $this->assertSame(
            0,
            LinkClickCounter::query()
                ->where('link_id', $link->id)
                ->where(fn ($q) => $q->where('slot', '<', 1)->orWhere('slot', '>', 100))
                ->count(),
        );
    }

    private function counterSum(Link $link, bool $isBot): int
    {
        return (int) LinkClickCounter::query()
            ->where('link_id', $link->id)
            ->where('is_bot', $isBot)
            ->sum('count');
    }

    private function recordClick(Link $link, ?string $userAgent, ?string $uuid = null): void
    {
        app(ClickRecorder::class)->record(
            $link,
            $uuid ?? (string) Str::uuid(),
            Url::factory()->create()->id,
            '203.0.113.10',
            null,
            $userAgent,
        );
    }
}
