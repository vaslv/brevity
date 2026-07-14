<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Clicks\Pages\ListClicks;
use App\Models\Click;
use App\Models\Link;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression for docs/07-plans.md — r46.
 *
 * The Clicks table made six relation columns searchable, so one search term
 * fanned out into OR'd leading-wildcard ILIKE whereHas subqueries over the
 * unbounded clicks table — a self-inflicted DoS at scale. Search is now limited
 * to link.code and ipAddress.value; heavy relation columns rely on filters.
 */
class ClicksTableSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_link_code_stays_searchable(): void
    {
        [$clickA, $clickB] = $this->twoClicks();

        Livewire::test(ListClicks::class)
            ->searchTable($clickA->link->code)
            ->assertCanSeeTableRecords([$clickA])
            ->assertCanNotSeeTableRecords([$clickB]);
    }

    public function test_url_value_is_no_longer_searchable(): void
    {
        [$clickA, $clickB] = $this->twoClicks();

        // The url substring belongs to clickA's target, but url.value is no
        // longer searchable, so it surfaces no rows at all.
        Livewire::test(ListClicks::class)
            ->searchTable('alpha.example')
            ->assertCanNotSeeTableRecords([$clickA, $clickB]);
    }

    /**
     * @return array{0: Click, 1: Click}
     */
    private function twoClicks(): array
    {
        $linkA = Link::factory()->create();
        $clickA = Click::factory()->for($linkA)->create([
            'service_id' => $linkA->service_id,
            'url_id' => Url::factory()->create(['value' => 'https://alpha.example/landing'])->id,
        ]);

        $linkB = Link::factory()->create();
        $clickB = Click::factory()->for($linkB)->create([
            'service_id' => $linkB->service_id,
            'url_id' => Url::factory()->create(['value' => 'https://beta.example/landing'])->id,
        ]);

        return [$clickA, $clickB];
    }
}
