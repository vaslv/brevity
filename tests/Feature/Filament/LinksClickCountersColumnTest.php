<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Links\Pages\ListLinks;
use App\Filament\Widgets\StatsOverview;
use App\Models\Link;
use App\Models\LinkClickCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Stage 1 of docs/07-plans.md — counter consumers in the admin panel.
 *
 * Link click totals in lists and dashboard cards read the pre-aggregated slot
 * counters (SUM over slots), never COUNT over the clicks table. A link's cell
 * shows the total with a non-bot breakdown; sorting uses the same aggregate.
 */
class LinksClickCountersColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_link_without_counters_shows_zero(): void
    {
        $link = Link::factory()->create();

        Livewire::test(ListLinks::class)
            ->assertTableColumnStateSet('click_counters_sum_count', 0, record: $link)
            ->assertSee(__('resources/link.fields.clicks_count_non_bots', ['count' => 0]));
    }

    public function test_clicks_column_sums_counter_slots(): void
    {
        $link = Link::factory()->create();
        // Two slots + a bot row: the column total must sum all of them.
        LinkClickCounter::query()->create(['link_id' => $link->id, 'is_bot' => false, 'slot' => 3, 'count' => 4]);
        LinkClickCounter::query()->create(['link_id' => $link->id, 'is_bot' => false, 'slot' => 7, 'count' => 5]);
        LinkClickCounter::query()->create(['link_id' => $link->id, 'is_bot' => true, 'slot' => 3, 'count' => 2]);

        Livewire::test(ListLinks::class)
            ->assertTableColumnStateSet('click_counters_sum_count', 11, record: $link);
    }

    public function test_links_sort_by_the_counter_aggregate(): void
    {
        $quiet = Link::factory()->create();
        $busy = Link::factory()->create();
        // No counters at all: aggregates to NULL and must sort as zero, not
        // above the busiest link (Postgres defaults to NULLS FIRST on DESC).
        $zero = Link::factory()->create();
        LinkClickCounter::query()->create(['link_id' => $busy->id, 'is_bot' => false, 'slot' => 1, 'count' => 9]);
        LinkClickCounter::query()->create(['link_id' => $quiet->id, 'is_bot' => false, 'slot' => 1, 'count' => 2]);

        Livewire::test(ListLinks::class)
            ->sortTable('click_counters_sum_count', 'desc')
            ->assertCanSeeTableRecords([$busy, $quiet, $zero], inOrder: true);

        // Both directions: rules out a coincidental match with insertion order
        // (a broken aggregate sort would fail one of the two).
        Livewire::test(ListLinks::class)
            ->sortTable('click_counters_sum_count', 'asc')
            ->assertCanSeeTableRecords([$zero, $quiet, $busy], inOrder: true);
    }

    public function test_stats_overview_reads_totals_from_counters(): void
    {
        $link = Link::factory()->create();
        LinkClickCounter::query()->create(['link_id' => $link->id, 'is_bot' => false, 'slot' => 1, 'count' => 7]);
        LinkClickCounter::query()->create(['link_id' => $link->id, 'is_bot' => true, 'slot' => 1, 'count' => 3]);

        Livewire::test(StatsOverview::class)
            ->assertSee(__('widgets.stats.clicks_total'))
            ->assertSee('10')
            ->assertSee(__('widgets.stats.clicks_total_non_bots', ['count' => 7]));
    }

    public function test_time_windowed_cards_stay_on_the_clicks_table(): void
    {
        // Counters without matching clicks: the all-time card must show them,
        // while «today» must stay at zero — proving the windowed cards still
        // COUNT over clicks(created_at) and did not silently move to counters.
        $link = Link::factory()->create();
        LinkClickCounter::query()->create(['link_id' => $link->id, 'is_bot' => false, 'slot' => 1, 'count' => 10]);

        Livewire::test(StatsOverview::class)
            ->assertSeeInOrder([__('widgets.stats.clicks_total'), '10'])
            ->assertSeeInOrder([__('widgets.stats.clicks_today'), '0']);
    }
}
