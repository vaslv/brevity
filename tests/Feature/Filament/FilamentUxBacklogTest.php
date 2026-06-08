<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Callbacks\Pages\ListCallbacks;
use App\Filament\Resources\Conditions\Pages\ListConditions;
use App\Filament\Resources\Links\Pages\EditLink;
use App\Filament\Resources\Links\RelationManagers\RulesRelationManager;
use App\Models\Callback;
use App\Models\Click;
use App\Models\Condition;
use App\Models\Link;
use App\Models\Service;
use App\Models\Url;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers docs/AUDIT_2026-06.md — Low: Filament UX.
 *
 * Enum/registry-backed columns searched the raw stored value instead of the
 * translated label the operator sees; the url_id rule select eagerly preloaded
 * the unbounded urls dictionary. Status/type now search by label, and the url
 * select loads lazily (creating a rule must still work).
 */
class FilamentUxBacklogTest extends TestCase
{
    use RefreshDatabase;

    public function test_callback_status_column_searches_by_translated_label(): void
    {
        $service = Service::query()->create(['name' => 'Svc '.fake()->unique()->word()]);
        $link = Link::query()->create(['service_id' => $service->id, 'forward_query' => false]);
        $url = Url::query()->create(['value' => 'https://example.com/'.fake()->unique()->slug()]);

        $sent = $this->createCallback($service, $link, $url, 'sent');
        $pending = $this->createCallback($service, $link, $url, 'pending');

        Livewire::test(ListCallbacks::class)
            ->searchTable(__('resources/callback.statuses.sent'))
            ->assertCanSeeTableRecords([$sent])
            ->assertCanNotSeeTableRecords([$pending]);
    }

    public function test_condition_type_column_searches_by_translated_label(): void
    {
        $condition = Condition::query()->create([
            'type' => 'time_before',
            'data' => ['before' => now()->addDay()->toIso8601String()],
        ]);

        Livewire::test(ListConditions::class)
            ->searchTable(__('resources/condition.types.time_before'))
            ->assertCanSeeTableRecords([$condition]);

        Livewire::test(ListConditions::class)
            ->searchTable('no-such-condition-label')
            ->assertCanNotSeeTableRecords([$condition]);
    }

    public function test_rule_is_created_after_url_select_loads_lazily(): void
    {
        $service = Service::query()->create(['name' => 'Svc '.fake()->unique()->word()]);
        $link = Link::query()->create(['service_id' => $service->id, 'forward_query' => false]);
        $url = Url::query()->create(['value' => 'https://example.com/'.fake()->unique()->slug()]);

        Livewire::test(RulesRelationManager::class, [
            'ownerRecord' => $link,
            'pageClass' => EditLink::class,
        ])
            ->callTableAction('create', data: [
                'url_id' => $url->id,
                'priority' => 1,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('rules', [
            'link_id' => $link->id,
            'url_id' => $url->id,
            'priority' => 1,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::query()->create([
            'name' => 'Admin',
            'email' => 'admin'.fake()->unique()->randomNumber().'@example.test',
            'password' => 'password',
        ]));
        Filament::setCurrentPanel('main');
        $this->app->setLocale('en');
    }

    private function createCallback(Service $service, Link $link, Url $url, string $status): Callback
    {
        $click = Click::query()->create([
            'uuid' => (string) Str::uuid(),
            'service_id' => $service->id,
            'link_id' => $link->id,
            'url_id' => $url->id,
        ]);

        return Callback::query()->create([
            'service_id' => $service->id,
            'click_id' => $click->id,
            'data' => [],
            'status' => $status,
            'attempts' => 0,
        ]);
    }
}
