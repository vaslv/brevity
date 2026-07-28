<?php

namespace Tests\Feature\Filament;

use App\Filament\Support\DashboardPeriod;
use App\Filament\Widgets\StatsOverview;
use App\Models\Callback;
use App\Models\Click;
use App\Models\Link;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Dashboard stat cards: links, clicks (bots included — a plain row count) and
 * callbacks over the page-level period, each compared against the previous
 * comparable window (an in-progress period is cut at the same elapsed time).
 */
class StatsOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_previous_period_shows_no_percent(): void
    {
        Link::factory()->create();

        Livewire::test(StatsOverview::class)
            ->assertSee(__('widgets.stats.previous_period_empty'));
    }

    public function test_callbacks_are_counted_within_the_period(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(12));

        Callback::factory()->count(2)->create();

        $old = Callback::factory()->create();
        Callback::query()->whereKey($old->id)->update(['created_at' => now()->subDays(40)]);

        $this->travel(5)->minutes();

        Livewire::test(StatsOverview::class, ['pageFilters' => ['period' => '30']])
            ->assertSee(__('widgets.stats.vs_previous_period', ['change' => '+100%', 'previous' => 1]));
    }

    public function test_default_period_compares_today_against_yesterday(): void
    {
        // Fixed mid-day time: keeps the test away from midnight edges, and the
        // later travel() keeps freshly created rows strictly inside the
        // half-open [from, now) window (created_at carries no microseconds, so
        // a row created in the render second would fall out of it).
        $this->travelTo(now()->startOfDay()->addHours(12));

        // Previous window is [yesterday 00:00, yesterday <current time>): a
        // click at yesterday's midnight is always inside it.
        $previous = Click::factory()->create();
        Click::query()->whereKey($previous->id)
            ->update(['created_at' => now()->subDay()->startOfDay()]);

        Click::factory()->count(3)->create();

        $this->travel(5)->minutes();

        Livewire::test(StatsOverview::class)
            ->assertSee(__('widgets.stats.vs_previous_period', ['change' => '+200%', 'previous' => 1]));
    }

    public function test_shows_the_three_period_cards(): void
    {
        Livewire::test(StatsOverview::class)
            ->assertSeeInOrder([
                __('widgets.stats.links'),
                __('widgets.stats.clicks'),
                __('widgets.stats.callbacks'),
            ]);
    }

    public function test_the_dashboard_renders_the_period_selector(): void
    {
        $this->actingAs(User::query()->create([
            'name' => 'Admin',
            'email' => 'admin'.fake()->unique()->randomNumber().'@example.test',
            'password' => 'password',
        ]));
        Filament::setCurrentPanel('main');

        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString(__('dashboard.periods.label'), $html);
        $this->assertStringContainsString(__('dashboard.periods.mtd'), $html);
        foreach (array_keys(DashboardPeriod::options()) as $key) {
            $this->assertStringContainsString('value="'.$key.'"', $html);
        }
    }

    public function test_the_page_period_filter_scopes_the_window(): void
    {
        $inside = Click::factory()->create();
        Click::query()->whereKey($inside->id)->update(['created_at' => now()->subDays(3)]);

        // Falls into the previous 7-day window, not the selected one.
        $before = Click::factory()->create();
        Click::query()->whereKey($before->id)->update(['created_at' => now()->subDays(10)]);

        Livewire::test(StatsOverview::class, ['pageFilters' => ['period' => '7']])
            ->assertSee(__('widgets.stats.vs_previous_period', ['change' => '0%', 'previous' => 1]));
    }
}
