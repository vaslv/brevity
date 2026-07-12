<?php

namespace Tests\Feature\Api;

use App\Models\Rule;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Stage 3 of docs/07-plans.md — A/B variants over the API (docs/03-api.md
 * §7.1): 2–20 weighted targets per rule, echoed in the response; validation
 * enforces the count and weights.
 */
class CreateLinkVariantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_non_positive_weight_is_rejected(): void
    {
        $this->createLink([
            'rules' => [[
                'url' => 'https://example.com/control',
                'variants' => [
                    ['url' => 'https://example.com/a', 'weight' => 0],
                    ['url' => 'https://example.com/b', 'weight' => 1],
                ],
            ]],
        ])->assertStatus(422);
    }

    public function test_a_non_web_variant_url_is_rejected(): void
    {
        $this->createLink([
            'rules' => [[
                'url' => 'https://example.com/control',
                'variants' => [
                    ['url' => 'javascript:alert(1)', 'weight' => 1],
                    ['url' => 'https://example.com/b', 'weight' => 1],
                ],
            ]],
        ])->assertStatus(422);
    }

    public function test_a_single_variant_is_rejected(): void
    {
        $this->createLink([
            'rules' => [[
                'url' => 'https://example.com/control',
                'variants' => [['url' => 'https://example.com/a', 'weight' => 1]],
            ]],
        ])->assertStatus(422);
    }

    public function test_variants_are_stored_and_echoed(): void
    {
        $response = $this->createLink([
            'rules' => [[
                'url' => 'https://example.com/control',
                'variants' => [
                    ['url' => 'https://example.com/a', 'weight' => 1, 'label' => 'A'],
                    ['url' => 'https://example.com/b', 'weight' => 3],
                ],
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonCount(2, 'data.rules.0.variants')
            ->assertJsonPath('data.rules.0.variants.0.label', 'A')
            ->assertJsonPath('data.rules.0.variants.1.weight', 3);

        $this->assertSame(2, Rule::query()->firstOrFail()->variants()->count());
    }

    private function createLink(array $payload): TestResponse
    {
        $service = Service::query()->create(['name' => 'Variant Service '.fake()->unique()->word()]);
        $token = $service->createToken('test', ['links:create'])->plainTextToken;

        return $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/links', $payload);
    }
}
