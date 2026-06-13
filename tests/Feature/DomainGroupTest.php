<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainGroup;
use App\Models\Relations\BelongsToManyDomainGroups;
use App\Models\Relations\BelongsToManyDomains;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The many-to-many link between domains and domain groups: a group holds a set
 * of domains, and the same domain may belong to several groups.
 *
 * @see DomainGroup
 * @see BelongsToManyDomains
 * @see BelongsToManyDomainGroups
 */
class DomainGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_domain_can_belong_to_several_groups(): void
    {
        $domain = Domain::factory()->create();
        $first = DomainGroup::factory()->create();
        $second = DomainGroup::factory()->create();

        $first->domains()->attach($domain);
        $second->domains()->attach($domain);

        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $domain->refresh()->domainGroups->pluck('id')->all(),
        );
    }

    public function test_a_group_holds_a_set_of_domains(): void
    {
        $group = DomainGroup::factory()->create();
        $domains = Domain::factory()->count(3)->create();

        $group->domains()->attach($domains);

        $this->assertEqualsCanonicalizing(
            $domains->pluck('id')->all(),
            $group->refresh()->domains->pluck('id')->all(),
        );
    }

    public function test_deleting_a_group_detaches_its_domains_without_deleting_them(): void
    {
        $domain = Domain::factory()->create();
        $group = DomainGroup::factory()->create();
        $group->domains()->attach($domain);

        $group->delete();

        $this->assertSame(0, DB::table('domain_domain_group')->count());
        $this->assertDatabaseHas('domains', ['id' => $domain->id]);
    }

    public function test_syncing_domains_does_not_create_duplicate_pivot_rows(): void
    {
        $domain = Domain::factory()->create();
        $group = DomainGroup::factory()->create();

        $group->domains()->sync([$domain->id]);
        $group->domains()->sync([$domain->id]);

        $this->assertSame(
            1,
            DB::table('domain_domain_group')->where('domain_group_id', $group->id)->count(),
        );
    }

    public function test_the_code_is_normalised_to_lower_case(): void
    {
        $group = DomainGroup::factory()->create(['code' => 'MixedCase']);

        $this->assertSame('mixedcase', $group->refresh()->code);
    }

    public function test_the_same_domains_can_be_attached_to_multiple_groups(): void
    {
        $domains = Domain::factory()->count(2)->create();
        $first = DomainGroup::factory()->create();
        $second = DomainGroup::factory()->create();

        $first->domains()->attach($domains);
        $second->domains()->attach($domains);

        $this->assertCount(2, $first->refresh()->domains);
        $this->assertCount(2, $second->refresh()->domains);
        $this->assertSame(4, DB::table('domain_domain_group')->count());
    }
}
