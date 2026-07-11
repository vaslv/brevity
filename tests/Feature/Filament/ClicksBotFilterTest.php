<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Clicks\Pages\ListClicks;
use App\Filament\Resources\UserAgents\Pages\ListUserAgents;
use App\Models\Click;
use App\Models\UserAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Stage 1 of docs/07-plans.md — the bot mark in the admin panel.
 *
 * The Clicks list shows a bot icon column (via the userAgent dictionary) and a
 * ternary filter; "not a bot" includes clicks without a user agent. The
 * UserAgents list exposes the flag as a column and filter.
 */
class ClicksBotFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_bot_filter_splits_clicks_by_their_user_agent_flag(): void
    {
        $botClick = Click::factory()->create([
            'user_agent_id' => UserAgent::factory()->bot()->create()->id,
        ]);
        $humanClick = Click::factory()->create([
            'user_agent_id' => UserAgent::factory()->create()->id,
        ]);
        $noUaClick = Click::factory()->create(['user_agent_id' => null]);

        Livewire::test(ListClicks::class)
            ->filterTable('is_bot', true)
            ->assertCanSeeTableRecords([$botClick])
            ->assertCanNotSeeTableRecords([$humanClick, $noUaClick]);

        Livewire::test(ListClicks::class)
            ->filterTable('is_bot', false)
            ->assertCanSeeTableRecords([$humanClick, $noUaClick])
            ->assertCanNotSeeTableRecords([$botClick]);
    }

    public function test_clicks_table_renders_the_bot_column(): void
    {
        Click::factory()->create([
            'user_agent_id' => UserAgent::factory()->bot()->create()->id,
        ]);

        Livewire::test(ListClicks::class)
            ->assertCanRenderTableColumn('userAgent.is_bot');
    }

    public function test_user_agents_table_filters_by_bot_flag(): void
    {
        $bot = UserAgent::factory()->bot()->create();
        $human = UserAgent::factory()->create();

        Livewire::test(ListUserAgents::class)
            ->assertCanRenderTableColumn('is_bot')
            ->filterTable('is_bot', true)
            ->assertCanSeeTableRecords([$bot])
            ->assertCanNotSeeTableRecords([$human]);

        Livewire::test(ListUserAgents::class)
            ->filterTable('is_bot', false)
            ->assertCanSeeTableRecords([$human])
            ->assertCanNotSeeTableRecords([$bot]);
    }
}
