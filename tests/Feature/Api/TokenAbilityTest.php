<?php

namespace Tests\Feature\Api;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guard for docs/CODE_REVIEW.md — m4.
 *
 * Service tokens are scoped to the `links:create` ability and the API route
 * enforces it. Wildcard ('*') tokens still pass (backward compatible).
 */
class TokenAbilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_with_links_create_ability_can_create_a_link(): void
    {
        $token = $this->serviceToken(['links:create']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/links', ['rules' => [['url' => 'https://example.com/x']]])
            ->assertCreated();
    }

    public function test_token_without_links_create_ability_is_forbidden(): void
    {
        $token = $this->serviceToken([]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/links', ['rules' => [['url' => 'https://example.com/x']]])
            ->assertForbidden();
    }

    public function test_wildcard_token_still_works(): void
    {
        $token = $this->serviceToken(['*']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/links', ['rules' => [['url' => 'https://example.com/x']]])
            ->assertCreated();
    }

    /**
     * @param  array<int, string>  $abilities
     */
    private function serviceToken(array $abilities): string
    {
        $service = Service::query()->create(['name' => 'Token Service '.fake()->unique()->word()]);

        return $service->createToken('test', $abilities)->plainTextToken;
    }
}
