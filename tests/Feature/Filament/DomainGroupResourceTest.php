<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\DomainGroups\Pages\CreateDomainGroup;
use App\Filament\Resources\DomainGroups\Pages\EditDomainGroup;
use App\Filament\Resources\DomainGroups\Pages\ListDomainGroups;
use App\Models\Domain;
use App\Models\DomainGroup;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The domain-group admin resource: create a group while attaching a set of
 * domains, edit the set, and list groups. Attaching domains is the panel-side
 * mechanism for the many-to-many relationship.
 */
class DomainGroupResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_group_is_created_with_attached_domains(): void
    {
        $domains = Domain::factory()->count(2)->create();

        Livewire::test(CreateDomainGroup::class)
            ->fillForm([
                'name' => 'Primary',
                'code' => 'primary',
                'domains' => $domains->pluck('id')->all(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $group = DomainGroup::query()->where('code', 'primary')->firstOrFail();

        $this->assertSame('Primary', $group->name);
        $this->assertEqualsCanonicalizing(
            $domains->pluck('id')->all(),
            $group->domains->pluck('id')->all(),
        );
    }

    public function test_editing_a_group_updates_its_domains(): void
    {
        $group = DomainGroup::factory()->create();
        $initial = Domain::factory()->create();
        $group->domains()->attach($initial);

        $replacement = Domain::factory()->create();

        Livewire::test(EditDomainGroup::class, ['record' => $group->getRouteKey()])
            ->fillForm(['domains' => [$replacement->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(
            [$replacement->id],
            $group->refresh()->domains->pluck('id')->all(),
        );
    }

    public function test_the_code_is_required(): void
    {
        // name left blank too, so the name->code auto-fill can't populate it.
        Livewire::test(CreateDomainGroup::class)
            ->fillForm(['name' => null, 'code' => null])
            ->call('create')
            ->assertHasFormErrors(['code' => 'required']);
    }

    public function test_the_list_page_shows_groups(): void
    {
        $groups = DomainGroup::factory()->count(3)->create();

        Livewire::test(ListDomainGroups::class)
            ->assertCanSeeTableRecords($groups);
    }

    public function test_the_name_is_required(): void
    {
        Livewire::test(CreateDomainGroup::class)
            ->fillForm(['name' => null])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
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
