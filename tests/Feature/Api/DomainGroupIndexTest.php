<?php

namespace Tests\Feature\Api;

use App\Models\Domain;
use App\Models\DomainGroup;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/domain-groups — list domain groups with their domain counts.
 */
class DomainGroupIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_404s_on_a_short_link_host(): void
    {
        $this->withToken($this->serviceToken())
            ->getJson(static::SHORT_LINK_HOST.'/api/domain-groups')
            ->assertNotFound();
    }

    public function test_it_lists_all_groups_with_domain_counts(): void
    {
        $alpha = DomainGroup::factory()->create(['name' => 'Alpha', 'code' => 'alpha']);
        DomainGroup::factory()->create(['name' => 'Beta', 'code' => 'beta']);
        $alpha->domains()->attach(Domain::factory()->count(2)->create());

        $this->withToken($this->serviceToken())
            ->getJson('/api/domain-groups')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['code', 'name', 'domains_count']]])
            ->assertJsonPath('data.0.code', 'alpha')
            ->assertJsonPath('data.0.name', 'Alpha')
            ->assertJsonPath('data.0.domains_count', 2)
            ->assertJsonPath('data.1.code', 'beta')
            ->assertJsonPath('data.1.name', 'Beta')
            ->assertJsonPath('data.1.domains_count', 0);
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/domain-groups')->assertUnauthorized();
    }

    public function test_it_requires_the_links_create_ability(): void
    {
        $this->withToken($this->serviceToken([]))
            ->getJson('/api/domain-groups')
            ->assertForbidden();
    }

    /**
     * @param  array<int, string>  $abilities
     */
    private function serviceToken(array $abilities = ['links:create']): string
    {
        $service = Service::query()->create(['name' => 'Svc '.fake()->unique()->word()]);

        return $service->createToken('test', $abilities)->plainTextToken;
    }
}
