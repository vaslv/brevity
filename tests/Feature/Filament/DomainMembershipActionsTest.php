<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\DomainGroups\Pages\ViewDomainGroup;
use App\Filament\Resources\DomainGroups\RelationManagers\DomainsRelationManager;
use App\Filament\Resources\Domains\Pages\ListDomains;
use App\Filament\Resources\Domains\Pages\ViewDomain;
use App\Filament\Resources\Domains\RelationManagers\DomainGroupsRelationManager;
use App\Models\Domain;
use App\Models\DomainGroup;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DomainMembershipActionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: int|null}>
     */
    public static function invalidGroups(): array
    {
        return [
            'missing group' => [null],
            'nonexistent group' => [2147483647],
        ];
    }

    public function test_a_domain_can_join_a_group_without_losing_existing_memberships(): void
    {
        $domain = Domain::factory()->create();
        $existing = DomainGroup::factory()->create();
        $target = DomainGroup::factory()->create();
        $domain->domainGroups()->attach($existing);

        Livewire::test(DomainGroupsRelationManager::class, [
            'ownerRecord' => $domain,
            'pageClass' => ViewDomain::class,
        ])
            ->callAction(TestAction::make('attachToGroup')->table(), ['recordId' => $target->id])
            ->assertHasNoActionErrors()
            ->assertNotified()
            ->assertCanSeeTableRecords([$existing, $target]);

        $this->assertEqualsCanonicalizing(
            [$existing->id, $target->id],
            $domain->domainGroups()->pluck('domain_groups.id')->all(),
        );
    }

    public function test_attaching_a_domain_to_the_same_group_twice_does_not_duplicate_the_membership(): void
    {
        $domain = Domain::factory()->create();
        $group = DomainGroup::factory()->create();
        $domain->domainGroups()->attach($group);

        Livewire::test(DomainGroupsRelationManager::class, [
            'ownerRecord' => $domain,
            'pageClass' => ViewDomain::class,
        ])
            ->callAction(TestAction::make('attachToGroup')->table(), ['recordId' => $group->id])
            ->assertHasNoActionErrors();

        $this->assertSame(1, $domain->domainGroups()->count());
    }

    public function test_bulk_attachment_adds_only_selected_domains_and_preserves_other_memberships(): void
    {
        $domains = Domain::factory()->count(2)->create();
        $existingMember = Domain::factory()->create();
        $unselected = Domain::factory()->create();
        $target = DomainGroup::factory()->create();
        $otherGroup = DomainGroup::factory()->create();
        $target->domains()->attach([$domains->first()->id, $existingMember->id]);
        $otherGroup->domains()->attach($domains->modelKeys());

        $page = Livewire::test(ListDomains::class);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $page->selectTableRecords($domains)
                ->callAction(TestAction::make('attachToGroup')->table()->bulk(), ['group_id' => $target->id])
                ->assertHasNoActionErrors()
                ->assertNotified()
                ->assertDispatched('deselectAllTableRecords');
        }

        $this->assertEqualsCanonicalizing(
            [...$domains->modelKeys(), $existingMember->id],
            $target->domains()->pluck('domains.id')->all(),
        );
        $this->assertEqualsCanonicalizing($domains->modelKeys(), $otherGroup->domains()->pluck('domains.id')->all());
        $this->assertSame(0, $unselected->domainGroups()->count());
    }

    #[DataProvider('invalidGroups')]
    public function test_bulk_attachment_requires_an_existing_group(?int $groupId): void
    {
        $domain = Domain::factory()->create();

        Livewire::test(ListDomains::class)
            ->selectTableRecords([$domain->id])
            ->callAction(TestAction::make('attachToGroup')->table()->bulk(), ['group_id' => $groupId])
            ->assertHasActionErrors(['group_id'])
            ->assertNotNotified();

        $this->assertSame(0, $domain->domainGroups()->count());
    }

    public function test_empty_membership_tables_explain_their_state(): void
    {
        Livewire::test(DomainGroupsRelationManager::class, [
            'ownerRecord' => Domain::factory()->create(),
            'pageClass' => ViewDomain::class,
        ])
            ->assertSee('This domain does not belong to any groups')
            ->assertActionVisible(TestAction::make('attachToGroup')->table());

        Livewire::test(DomainsRelationManager::class, [
            'ownerRecord' => DomainGroup::factory()->create(),
            'pageClass' => ViewDomainGroup::class,
        ])->assertSee('No domains');
    }

    #[DataProvider('invalidGroups')]
    public function test_single_attachment_requires_an_existing_group(?int $groupId): void
    {
        $domain = Domain::factory()->create();

        Livewire::test(DomainGroupsRelationManager::class, [
            'ownerRecord' => $domain,
            'pageClass' => ViewDomain::class,
        ])
            ->callAction(TestAction::make('attachToGroup')->table(), ['recordId' => $groupId])
            ->assertHasActionErrors(['recordId'])
            ->assertNotNotified();

        $this->assertSame(0, $domain->domainGroups()->count());
    }

    public function test_the_domain_lists_only_its_groups_and_can_detach_one(): void
    {
        $domain = Domain::factory()->create();
        $otherDomain = Domain::factory()->create();
        $groups = DomainGroup::factory()->count(2)->create();
        $unrelated = DomainGroup::factory()->create();
        $domain->domainGroups()->attach($groups->modelKeys());
        $otherDomain->domainGroups()->attach($groups->first());

        Livewire::test(DomainGroupsRelationManager::class, [
            'ownerRecord' => $domain,
            'pageClass' => ViewDomain::class,
        ])
            ->assertCanSeeTableRecords($groups)
            ->assertCanNotSeeTableRecords([$unrelated])
            ->callAction(TestAction::make('detach')->table($groups->first()))
            ->assertHasNoActionErrors()
            ->assertNotified()
            ->assertCanNotSeeTableRecords([$groups->first()])
            ->assertCanSeeTableRecords([$groups->last()]);

        $this->assertEquals([$groups->last()->id], $domain->domainGroups()->pluck('domain_groups.id')->all());
        $this->assertTrue($otherDomain->domainGroups()->whereKey($groups->first())->exists());
        $this->assertModelExists($domain);
        $this->assertModelExists($groups->first());
    }

    public function test_the_domain_view_shows_its_group_manager(): void
    {
        $domain = Domain::factory()->create();

        Livewire::test(ViewDomain::class, ['record' => $domain->getRouteKey()])
            ->assertOk()
            ->assertSeeLivewire(DomainGroupsRelationManager::class);
    }

    public function test_the_group_lists_only_its_domains_and_can_detach_one(): void
    {
        $group = DomainGroup::factory()->create();
        $otherGroup = DomainGroup::factory()->create();
        $domains = Domain::factory()->count(2)->create();
        $unrelated = Domain::factory()->create();
        $group->domains()->attach($domains->modelKeys());
        $otherGroup->domains()->attach($domains->first());

        Livewire::test(DomainsRelationManager::class, [
            'ownerRecord' => $group,
            'pageClass' => ViewDomainGroup::class,
        ])
            ->assertCanSeeTableRecords($domains)
            ->assertCanNotSeeTableRecords([$unrelated])
            ->callAction(TestAction::make('detach')->table($domains->first()))
            ->assertHasNoActionErrors()
            ->assertNotified()
            ->assertCanNotSeeTableRecords([$domains->first()])
            ->assertCanSeeTableRecords([$domains->last()]);

        $this->assertEquals([$domains->last()->id], $group->domains()->pluck('domains.id')->all());
        $this->assertTrue($otherGroup->domains()->whereKey($domains->first())->exists());
        $this->assertModelExists($group);
        $this->assertModelExists($domains->first());
    }

    public function test_the_group_view_shows_its_domain_manager(): void
    {
        $group = DomainGroup::factory()->create();

        Livewire::test(ViewDomainGroup::class, ['record' => $group->getRouteKey()])
            ->assertOk()
            ->assertSeeLivewire(DomainsRelationManager::class);
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
