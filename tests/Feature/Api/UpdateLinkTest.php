<?php

namespace Tests\Feature\Api;

use App\Models\Link;
use App\Models\Rule;
use App\Models\Service;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Stage 2 of docs/07-plans.md — PATCH /api/v1/links/{code}
 * (docs/03-api.md §5.2): sentinel semantics (absent key = untouched, explicit
 * null = cleared), full replacement of the rule set when `rules` is present,
 * merged-window consistency, tenant scope and the links:update ability.
 */
class UpdateLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_foreign_link_is_404(): void
    {
        $owner = $this->makeService();
        $stranger = $this->makeService();
        $link = $this->makeLink($owner);

        $this->patchLink($stranger, $link->code, ['title' => 'X'])
            ->assertStatus(404)
            ->assertJson(['type' => 'not-found']);
    }

    public function test_a_token_without_links_update_is_forbidden(): void
    {
        $service = $this->makeService();
        $link = $this->makeLink($service);
        $token = $service->createToken('legacy', ['links:create'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/links/'.$link->code, ['title' => 'X'])
            ->assertStatus(403)
            ->assertJson(['type' => 'missing-ability']);
    }

    public function test_absent_keys_stay_untouched_and_null_clears(): void
    {
        $service = $this->makeService();
        $link = $this->makeLink($service, [
            'title' => 'Original',
            'max_clicks' => 50,
            'valid_until' => now()->addMonth(),
        ]);

        // Change the title only: limits must survive.
        $this->patchLink($service, $link->code, ['title' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Renamed')
            ->assertJsonPath('data.max_clicks', 50);

        // Explicit nulls clear the limits.
        $this->patchLink($service, $link->code, ['max_clicks' => null, 'valid_until' => null])
            ->assertOk()
            ->assertJsonPath('data.max_clicks', null)
            ->assertJsonPath('data.valid_until', null)
            ->assertJsonPath('data.title', 'Renamed');
    }

    public function test_an_empty_patch_is_a_no_op(): void
    {
        $service = $this->makeService();
        $link = $this->makeLink($service, ['title' => 'Untouched']);

        $this->patchLink($service, $link->code, [])
            ->assertOk()
            ->assertJsonPath('data.title', 'Untouched');
    }

    public function test_forward_query_null_clears_to_false(): void
    {
        $service = $this->makeService();
        $link = $this->makeLink($service, ['forward_query' => true]);

        // The column is NOT NULL: null "clears" the boolean to its default.
        $this->patchLink($service, $link->code, ['forward_query' => null])
            ->assertOk()
            ->assertJsonPath('data.forward_query', false);
    }

    public function test_immutable_fields_are_ignored(): void
    {
        $service = $this->makeService();
        $link = $this->makeLink($service);

        $this->patchLink($service, $link->code, ['domain' => 'evil.example', 'code' => 'hacked'])
            ->assertOk()
            ->assertJsonPath('data.code', $link->code)
            ->assertJsonPath('data.domain', null);
    }

    public function test_merged_window_inconsistency_is_rejected(): void
    {
        $service = $this->makeService();
        $link = $this->makeLink($service, ['valid_since' => now()->addMonth()]);

        // Stored valid_since is a month away; a valid_until before it must 422
        // even though the payload alone looks consistent.
        $this->patchLink($service, $link->code, [
            'valid_until' => now()->addDay()->format('Y-m-d\TH:i:sP'),
        ])->assertStatus(422)->assertJsonPath('type', 'validation-error');
    }

    public function test_patch_replaces_the_variant_set(): void
    {
        $service = $this->makeService();
        $link = $this->makeLink($service);

        $this->patchLink($service, $link->code, [
            'rules' => [[
                'url' => 'https://example.com/control',
                'variants' => [
                    ['url' => 'https://example.com/a', 'weight' => 2, 'label' => 'A'],
                    ['url' => 'https://example.com/b', 'weight' => 5, 'label' => 'B'],
                ],
            ]],
        ])->assertOk()
            ->assertJsonCount(2, 'data.rules.0.variants')
            ->assertJsonPath('data.rules.0.variants.1.label', 'B');

        $this->assertSame(2, $link->rules()->firstOrFail()->variants()->count());
    }

    public function test_rules_are_replaced_as_a_whole_ordered_set(): void
    {
        $service = $this->makeService();
        $link = $this->makeLink($service);

        $this->patchLink($service, $link->code, [
            'rules' => [
                ['url' => 'https://example.com/new-first', 'transition_mode' => 'delayed'],
                ['url' => 'https://example.com/new-second'],
            ],
        ])->assertOk()
            ->assertJsonCount(2, 'data.rules')
            ->assertJsonPath('data.rules.0.url', 'https://example.com/new-first')
            ->assertJsonPath('data.rules.0.transition_mode', 'delayed');

        $this->assertSame(
            [1, 2],
            $link->rules()->orderBy('priority')->pluck('priority')->all(),
        );
    }

    private function makeLink(Service $service, array $attributes = []): Link
    {
        $link = Link::factory()->create(array_merge(['service_id' => $service->id], $attributes));
        $link->update(['code' => fake()->unique()->bothify('????####')]);

        Rule::factory()->create([
            'link_id' => $link->id,
            'url_id' => Url::factory()->create()->id,
            'priority' => 1,
        ]);

        return $link->refresh();
    }

    private function makeService(): Service
    {
        return Service::query()->create(['name' => 'Update Service '.fake()->unique()->word()]);
    }

    private function patchLink(Service $service, string $code, array $payload): TestResponse
    {
        $token = $service->createToken('test', ['links:update'])->plainTextToken;

        return $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/links/'.$code, $payload);
    }
}
