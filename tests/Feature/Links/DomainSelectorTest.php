<?php

namespace Tests\Feature\Links;

use App\Models\Domain;
use App\Models\DomainGroup;
use App\Models\Link;
use App\Services\Links\Domains\DomainSelectionStrategy;
use App\Services\Links\Domains\DomainSelector;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The strategies that pick a domain when a link is created without an explicit
 * one: random, round-robin (least recently assigned) and coldest (fewest links
 * in the rolling window). Each runs over a pool — a group, or all domains.
 *
 * @see DomainSelector
 */
class DomainSelectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_coldest_ignores_links_outside_the_window(): void
    {
        config(['domains.coldest_period_days' => 7]);

        $a = Domain::factory()->create();
        $b = Domain::factory()->create();

        // a is busy but only OUTSIDE the window, so it has zero links in-window.
        $this->assignLink($a, now()->subDays(30));
        $this->assignLink($a, now()->subDays(30));
        $this->assignLink($b, now()->subDay());

        $this->assertSame($a->id, $this->selector()->select(DomainSelectionStrategy::Coldest)?->id);
    }

    public function test_coldest_is_scoped_to_the_given_group(): void
    {
        config(['domains.coldest_period_days' => 30]);

        $group = DomainGroup::factory()->create();
        $inQuiet = Domain::factory()->create();
        $inBusy = Domain::factory()->create();
        $group->domains()->attach([$inQuiet->id, $inBusy->id]);

        // A globally-coldest domain that is NOT in the group must be ignored.
        Domain::factory()->create();

        $this->assignLink($inBusy, now()->subDay());

        $this->assertSame(
            $inQuiet->id,
            $this->selector()->select(DomainSelectionStrategy::Coldest, $group->code)?->id,
        );
    }

    public function test_coldest_returns_the_domain_with_the_fewest_recent_links(): void
    {
        config(['domains.coldest_period_days' => 30]);

        $busy = Domain::factory()->create();
        $quiet = Domain::factory()->create();

        $this->assignLink($busy, now()->subDay());
        $this->assignLink($busy, now()->subDay());
        $this->assignLink($busy, now()->subDay());
        $this->assignLink($quiet, now()->subDay());

        $this->assertSame($quiet->id, $this->selector()->select(DomainSelectionStrategy::Coldest)?->id);
    }

    public function test_it_returns_null_when_the_pool_is_empty(): void
    {
        $this->assertNull($this->selector()->select(DomainSelectionStrategy::Random));

        $emptyGroup = DomainGroup::factory()->create();
        Domain::factory()->count(2)->create(); // exist, but not in the group

        $this->assertNull($this->selector()->select(DomainSelectionStrategy::Random, $emptyGroup->code));
    }

    public function test_random_is_scoped_to_the_given_group(): void
    {
        $group = DomainGroup::factory()->create();
        $inGroup = Domain::factory()->count(2)->create();
        Domain::factory()->count(3)->create(); // outside the group
        $group->domains()->attach($inGroup);

        for ($i = 0; $i < 12; $i++) {
            $selected = $this->selector()->select(DomainSelectionStrategy::Random, $group->code);

            $this->assertTrue($inGroup->pluck('id')->contains($selected->id));
        }
    }

    public function test_random_picks_a_domain_from_all_when_no_group_is_given(): void
    {
        $domains = Domain::factory()->count(3)->create();

        $selected = $this->selector()->select(DomainSelectionStrategy::Random);

        $this->assertNotNull($selected);
        $this->assertTrue($domains->pluck('id')->contains($selected->id));
    }

    public function test_round_robin_returns_the_least_recently_assigned_then_cycles(): void
    {
        $a = Domain::factory()->create();
        $b = Domain::factory()->create();
        $c = Domain::factory()->create();

        $this->assignLink($a, now()->subHours(3));
        $this->assignLink($b, now()->subHours(2));
        // c has never been assigned -> it is the least recently used.

        $this->assertSame($c->id, $this->selector()->select(DomainSelectionStrategy::RoundRobin)?->id);

        // Assign c now; the next least-recently-used becomes a.
        $this->assignLink($c, now());

        $this->assertSame($a->id, $this->selector()->select(DomainSelectionStrategy::RoundRobin)?->id);
    }

    private function assignLink(Domain $domain, CarbonInterface $at): void
    {
        $link = Link::factory()->forDomain($domain)->create();

        DB::table('links')->where('id', $link->id)->update(['created_at' => $at]);
    }

    private function selector(): DomainSelector
    {
        return app(DomainSelector::class);
    }
}
