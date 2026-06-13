<?php

namespace Tests\Feature\Api;

use App\Models\Domain;
use App\Models\DomainGroup;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/domains — list the domain catalog, optionally scoped to a group.
 */
class DomainIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unknown_group_id_is_rejected(): void
    {
        $this->withToken($this->serviceToken())
            ->getJson('/api/domains?group_id=999999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['group_id']);
    }

    public function test_it_404s_on_a_short_link_host(): void
    {
        $this->withToken($this->serviceToken())
            ->getJson(static::SHORT_LINK_HOST.'/api/domains')
            ->assertNotFound();
    }

    public function test_it_lists_all_domains_without_a_group_filter(): void
    {
        Domain::factory()->create(['value' => 'a.example.com']);
        Domain::factory()->create(['value' => 'b.example.com']);

        $this->withToken($this->serviceToken())
            ->getJson('/api/domains')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['domain', 'url', 'is_default']]])
            ->assertJsonPath('data.0.domain', 'a.example.com')
            ->assertJsonPath('data.0.url', 'https://a.example.com')
            ->assertJsonPath('data.1.domain', 'b.example.com');
    }

    public function test_it_lists_only_the_domains_in_the_requested_group(): void
    {
        $group = DomainGroup::factory()->create();
        $inGroup = Domain::factory()->create(['value' => 'in.example.com']);
        Domain::factory()->create(['value' => 'out.example.com']);
        $group->domains()->attach($inGroup);

        $this->withToken($this->serviceToken())
            ->getJson('/api/domains?group_id='.$group->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.domain', 'in.example.com');
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/domains')->assertUnauthorized();
    }

    public function test_it_requires_the_links_create_ability(): void
    {
        $this->withToken($this->serviceToken([]))
            ->getJson('/api/domains')
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
