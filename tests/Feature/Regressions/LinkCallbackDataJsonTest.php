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
 * Guard for docs/08-decisions.md (review 2026-06) — m13.
 *
 * The admin callback_data field is a JSON editor (not a flat KeyValue), so
 * nested templates round-trip without being flattened/corrupted.
 */
class LinkCallbackDataJsonTest extends TestCase
{
    use RefreshDatabase;

    public function test_nested_callback_data_round_trips_through_the_admin_form(): void
    {
        $this->actingAs(User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]));
        Filament::setCurrentPanel('main');

        $service = Service::query()->create(['name' => 'Svc '.fake()->unique()->word()]);
        $link = Link::query()->create([
            'service_id' => $service->id,
            'forward_query' => false,
            'callback_data' => ['campaign' => 'x', 'meta' => ['ref' => 'y']],
        ]);

        Livewire::test(EditLink::class, ['record' => $link->getRouteKey()])
            ->fillForm(['callback_data' => '{"campaign":"x","meta":{"ref":"z"}}'])
            ->call('save')
            ->assertHasNoFormErrors();

        // jsonb does not preserve key order, so compare order-insensitively.
        $this->assertEquals(
            ['campaign' => 'x', 'meta' => ['ref' => 'z']],
            $link->refresh()->callback_data,
        );
    }
}
