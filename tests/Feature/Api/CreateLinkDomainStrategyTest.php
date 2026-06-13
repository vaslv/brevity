<?php

namespace Tests\Feature\Api;

use App\Models\Domain;
use App\Models\DomainGroup;
use App\Models\Link;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * POST /api/links — automatic domain selection by strategy, optionally scoped
 * to a domain group, when the request does not name an explicit domain.
 */
class CreateLinkDomainStrategyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_group_without_a_strategy_is_rejected(): void
    {
        $group = DomainGroup::factory()->create();

        $this->postLink(['domain_group_id' => $group->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['domain_strategy']);
    }

    public function test_a_strategy_over_an_empty_group_is_rejected(): void
    {
        $group = DomainGroup::factory()->create(); // no domains attached
        Domain::factory()->create(); // exists, but not in the group

        $this->postLink(['domain_strategy' => 'random', 'domain_group_id' => $group->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['domain_group_id']);
    }

    public function test_a_strategy_with_no_domains_available_is_rejected(): void
    {
        $this->postLink(['domain_strategy' => 'random'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['domain_strategy']);
    }

    public function test_an_explicit_domain_cannot_be_combined_with_a_strategy(): void
    {
        Domain::factory()->create(['value' => 'explicit.example.com']);

        $this->postLink([
            'domain' => 'explicit.example.com',
            'domain_strategy' => 'random',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['domain']);
    }

    public function test_an_unknown_group_is_rejected(): void
    {
        $this->postLink(['domain_strategy' => 'random', 'domain_group_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['domain_group_id']);
    }

    public function test_an_unknown_strategy_is_rejected(): void
    {
        $this->postLink(['domain_strategy' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['domain_strategy']);
    }

    public function test_coldest_strategy_picks_the_least_used_domain(): void
    {
        $busy = Domain::factory()->create(['value' => 'busy.example.com']);
        $quiet = Domain::factory()->create(['value' => 'quiet.example.com']);

        foreach (range(1, 3) as $ignored) {
            $link = Link::factory()->forDomain($busy)->create();
            DB::table('links')->where('id', $link->id)->update(['created_at' => now()->subDay()]);
        }

        $this->postLink(['domain_strategy' => 'coldest'])
            ->assertCreated()
            ->assertJsonPath('data.domain', 'quiet.example.com');
    }

    public function test_random_strategy_assigns_a_domain_from_all_domains(): void
    {
        $domains = Domain::factory()->count(3)->create();

        $domain = $this->postLink(['domain_strategy' => 'random'])
            ->assertCreated()
            ->json('data.domain');

        $this->assertContains($domain, $domains->pluck('value')->all());
    }

    public function test_round_robin_strategy_rotates_across_domains(): void
    {
        Domain::factory()->create(['value' => 'a.example.com']);
        Domain::factory()->create(['value' => 'b.example.com']);

        $first = $this->postLink(['domain_strategy' => 'round_robin'])
            ->assertCreated()->json('data.domain');
        $second = $this->postLink(['domain_strategy' => 'round_robin'])
            ->assertCreated()->json('data.domain');

        $this->assertNotSame($first, $second);
        $this->assertEqualsCanonicalizing(
            ['a.example.com', 'b.example.com'],
            [$first, $second],
        );
    }

    public function test_strategy_is_scoped_to_the_given_group(): void
    {
        $group = DomainGroup::factory()->create();
        $inGroup = Domain::factory()->create(['value' => 'in.example.com']);
        Domain::factory()->create(['value' => 'out.example.com']);
        $group->domains()->attach($inGroup);

        $this->postLink(['domain_strategy' => 'random', 'domain_group_id' => $group->id])
            ->assertCreated()
            ->assertJsonPath('data.domain', 'in.example.com');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function postLink(array $data): TestResponse
    {
        $service = Service::query()->create(['name' => 'Svc '.fake()->unique()->word()]);
        $token = $service->createToken('test')->plainTextToken;

        return $this->withToken($token)->postJson('/api/links', [
            'rules' => [['url' => 'https://example.com/landing']],
            ...$data,
        ]);
    }
}
