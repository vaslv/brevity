<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Domains\Pages\ViewDomain;
use App\Models\Domain;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The domain view page exposes a "set as default" header action so an operator
 * can pick the default domain (used for links created without an explicit one)
 * from the panel. The list page deliberately has no such action.
 *
 * @see ViewDomain
 */
class SetDefaultDomainActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_action_is_hidden_when_the_domain_is_already_default(): void
    {
        $domain = Domain::query()->create(['value' => 'already-default.test', 'is_default' => true]);

        Livewire::test(ViewDomain::class, ['record' => $domain->getRouteKey()])
            ->assertActionHidden('setAsDefault');
    }

    public function test_the_action_is_visible_on_a_non_default_domain(): void
    {
        $domain = Domain::query()->create(['value' => 'regular.test']);

        Livewire::test(ViewDomain::class, ['record' => $domain->getRouteKey()])
            ->assertActionVisible('setAsDefault');
    }

    public function test_the_action_promotes_the_domain_and_demotes_the_previous_default(): void
    {
        $previous = Domain::query()->create(['value' => 'old-default.test', 'is_default' => true]);
        $domain = Domain::query()->create(['value' => 'new-default.test']);

        Livewire::test(ViewDomain::class, ['record' => $domain->getRouteKey()])
            ->callAction('setAsDefault')
            ->assertHasNoActionErrors()
            ->assertNotified();

        $this->assertTrue($domain->refresh()->is_default);
        $this->assertFalse($previous->refresh()->is_default);
        $this->assertSame($domain->id, Domain::default()?->id);
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
}
