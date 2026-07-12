<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Links\Pages\EditLink;
use App\Filament\Resources\Links\Pages\ListLinks;
use App\Models\Link;
use App\Models\LinkClickCounter;
use Filament\Actions\DeleteAction;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Stage 2 of docs/07-plans.md — lifecycle in the admin panel: editable
 * valid_since / valid_until / max_clicks, the "alive only" list filter with
 * the same semantics the resolver enforces, and the deletion-threshold
 * warning in the confirmation modal.
 */
class LinkLifecycleAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_input_is_interpreted_in_the_panel_timezone(): void
    {
        // Operator in Europe/Moscow (+03:00) enters 12:00 — it must store as
        // 09:00 UTC, not 12:00 UTC.
        FilamentTimezone::set('Europe/Moscow');
        $link = Link::factory()->create();

        Livewire::test(EditLink::class, ['record' => $link->id])
            ->fillForm(['valid_since' => '2026-08-01 12:00:00'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(
            '2026-08-01T09:00:00+00:00',
            $link->refresh()->valid_since?->utc()->toIso8601String(),
        );
    }

    public function test_delete_modal_stays_calm_below_the_threshold(): void
    {
        config()->set('link.delete_confirm_threshold', 15);

        $quiet = Link::factory()->create();
        LinkClickCounter::query()->create(['link_id' => $quiet->id, 'is_bot' => false, 'slot' => 1, 'count' => 3]);

        Livewire::test(EditLink::class, ['record' => $quiet->id])
            ->mountAction(DeleteAction::class)
            ->assertMountedActionModalDontSee(__('resources/link.delete.threshold_warning', ['count' => 3]));
    }

    public function test_delete_modal_warns_above_the_click_threshold(): void
    {
        config()->set('link.delete_confirm_threshold', 15);

        $busy = Link::factory()->create();
        LinkClickCounter::query()->create(['link_id' => $busy->id, 'is_bot' => false, 'slot' => 1, 'count' => 20]);

        Livewire::test(EditLink::class, ['record' => $busy->id])
            ->mountAction(DeleteAction::class)
            ->assertMountedActionModalSee(__('resources/link.delete.threshold_warning', ['count' => 20]));
    }

    public function test_lifecycle_fields_are_editable_in_the_form(): void
    {
        $link = Link::factory()->create();

        Livewire::test(EditLink::class, ['record' => $link->id])
            ->fillForm([
                'valid_until' => now()->addDays(7)->format('Y-m-d H:i'),
                'max_clicks' => 500,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $link->refresh();
        $this->assertNotNull($link->valid_until);
        $this->assertSame(500, $link->max_clicks);
    }

    public function test_only_alive_filter_hides_dead_links(): void
    {
        $alive = Link::factory()->create();
        $scheduled = Link::factory()->scheduled()->create();
        $expired = Link::factory()->expired()->create();
        $exhausted = Link::factory()->withMaxClicks(1)->create();
        LinkClickCounter::query()->create(['link_id' => $exhausted->id, 'is_bot' => false, 'slot' => 1, 'count' => 1]);

        Livewire::test(ListLinks::class)
            ->filterTable('only_alive')
            ->assertCanSeeTableRecords([$alive])
            ->assertCanNotSeeTableRecords([$scheduled, $expired, $exhausted]);
    }

    public function test_valid_until_before_valid_since_is_rejected_in_the_form(): void
    {
        $link = Link::factory()->create(['valid_since' => now()->addMonth()]);

        Livewire::test(EditLink::class, ['record' => $link->id])
            ->fillForm(['valid_until' => now()->addDay()->format('Y-m-d H:i')])
            ->call('save')
            ->assertHasFormErrors(['valid_until']);
    }
}
