<?php

namespace Tests\Feature\Clicks;

use App\Models\Click;
use App\Models\Link;
use App\Models\LinkClickCounter;
use App\Models\UserAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Stage 1 of docs/07-plans.md — the clicks:rebuild-counters command.
 *
 * The rebuild replaces link_click_counters with an aggregate computed from the
 * clicks table (initial backfill and reconciliation after bulk changes): the
 * sums must converge with COUNT per (link, is_bot), clicks without a user
 * agent count as non-bot, and stale counter rows must not survive.
 */
class RebuildClickCountersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_aborts_when_another_run_holds_the_lock(): void
    {
        $link = Link::factory()->create();
        Click::factory()->create(['link_id' => $link->id, 'user_agent_id' => null]);

        $lock = Cache::lock('clicks:rebuild-counters', 60);
        $this->assertTrue($lock->get());

        try {
            $this->artisan('clicks:rebuild-counters')->assertFailed();
        } finally {
            $lock->release();
        }

        $this->assertSame(0, (int) LinkClickCounter::query()->count());
    }

    public function test_rebuild_converges_with_click_counts(): void
    {
        $link = Link::factory()->create();
        $otherLink = Link::factory()->create();
        $botUa = UserAgent::factory()->bot()->create();
        $humanUa = UserAgent::factory()->create();

        Click::factory()->count(3)->create(['link_id' => $link->id, 'user_agent_id' => $humanUa->id]);
        Click::factory()->count(2)->create(['link_id' => $link->id, 'user_agent_id' => $botUa->id]);
        // No user agent → counts as non-bot.
        Click::factory()->create(['link_id' => $link->id, 'user_agent_id' => null]);
        Click::factory()->create(['link_id' => $otherLink->id, 'user_agent_id' => $humanUa->id]);

        // A stale counter row that must not survive the rebuild.
        LinkClickCounter::query()->create([
            'link_id' => $link->id, 'is_bot' => false, 'slot' => 42, 'count' => 99,
        ]);

        $this->artisan('clicks:rebuild-counters')->assertSuccessful();

        $this->assertSame(4, $this->counterSum($link->id, isBot: false));
        $this->assertSame(2, $this->counterSum($link->id, isBot: true));
        $this->assertSame(1, $this->counterSum($otherLink->id, isBot: false));
        $this->assertSame(
            0,
            (int) LinkClickCounter::query()->where('slot', '!=', 1)->count(),
            'Rebuild writes everything into slot 1; stale rows must be gone.',
        );
    }

    public function test_rebuild_of_an_empty_clicks_table_leaves_no_counters(): void
    {
        $link = Link::factory()->create();
        LinkClickCounter::query()->create([
            'link_id' => $link->id, 'is_bot' => false, 'slot' => 1, 'count' => 5,
        ]);

        $this->artisan('clicks:rebuild-counters')
            ->expectsOutputToContain('0 row(s)')
            ->assertSuccessful();

        $this->assertSame(0, (int) LinkClickCounter::query()->count());
    }

    private function counterSum(int $linkId, bool $isBot): int
    {
        return (int) LinkClickCounter::query()
            ->where('link_id', $linkId)
            ->where('is_bot', $isBot)
            ->sum('count');
    }
}
