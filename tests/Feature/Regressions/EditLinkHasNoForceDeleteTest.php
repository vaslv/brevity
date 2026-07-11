<?php

namespace Tests\Feature\Regressions;

use App\Filament\Resources\Links\Pages\EditLink;
use App\Models\Link;
use App\Models\Service;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression for docs/08-decisions.md (audit 2026-06) — M8.
 *
 * The Link edit page exposed a ForceDeleteAction, but clicks.link_id is
 * restrictOnDelete and clicks/callbacks must outlive a link — so force-deleting
 * a link with clicks raised a raw FK QueryException. Force delete must not be
 * offered (consistent with LinksTable).
 */
class EditLinkHasNoForceDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_force_delete_is_not_available_on_the_link_edit_page(): void
    {
        $user = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);
        $service = Service::query()->create(['name' => 'Svc '.fake()->unique()->word()]);
        $link = Link::query()->create(['service_id' => $service->id, 'forward_query' => false]);

        $this->actingAs($user);
        Filament::setCurrentPanel('main');

        Livewire::test(EditLink::class, ['record' => $link->getRouteKey()])
            ->assertActionDoesNotExist('forceDelete')
            ->assertActionExists('delete');
    }
}
