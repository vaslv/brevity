<?php

namespace Tests\Feature\Api;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stage 2 of docs/07-plans.md — the /api/v1 skeleton with RFC 7807 errors.
 *
 * /api/v1 serves the same endpoints as legacy /api but renders every error as
 * application/problem+json with a stable machine code in `type`
 * (docs/03-api.md §11). Legacy unversioned routes keep the old Laravel error
 * shape — frozen until clients migrate (docs/08-decisions.md, 2026-07-12).
 */
class ApiV1ProblemDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_routes_keep_the_old_error_shape(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->serviceToken(['links:create']))
            ->postJson('/api/links', []);

        $response->assertStatus(422)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonMissingPath('type');
    }

    public function test_v1_creates_a_link_with_the_same_success_contract(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->serviceToken(['links:create']))
            ->postJson('/api/v1/links', ['rules' => [['url' => 'https://example.com/x']]]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['url', 'domain', 'code', 'rules']]);
    }

    public function test_v1_missing_ability_is_problem_json(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->serviceToken([]))
            ->postJson('/api/v1/links', ['rules' => [['url' => 'https://example.com/x']]])
            ->assertStatus(403)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson(['type' => 'missing-ability', 'status' => 403]);
    }

    public function test_v1_missing_token_is_problem_json_unauthenticated(): void
    {
        $this->postJson('/api/v1/links', ['rules' => [['url' => 'https://example.com/x']]])
            ->assertStatus(401)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson(['type' => 'unauthenticated', 'status' => 401]);
    }

    public function test_v1_on_a_short_link_host_is_problem_json_not_found(): void
    {
        // EnsureTechnicalHost rejects API calls on redirect-serving hosts
        // before auth; for /api/v1 the 404 still speaks problem+json.
        $this->postJson(static::SHORT_LINK_HOST.'/api/v1/links', [])
            ->assertStatus(404)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson(['type' => 'not-found', 'status' => 404]);
    }

    public function test_v1_serves_the_domain_catalog(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->serviceToken(['links:create']))
            ->getJson('/api/v1/domains')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_v1_unknown_path_is_problem_json_not_found(): void
    {
        $this->getJson('/api/v1/nope')
            ->assertStatus(404)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson(['type' => 'not-found', 'status' => 404]);
    }

    public function test_v1_validation_error_is_problem_json_with_stable_code(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->serviceToken(['links:create']))
            ->postJson('/api/v1/links', []);

        $response->assertStatus(422)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson([
                'type' => 'validation-error',
                'status' => 422,
            ])
            ->assertJsonStructure(['type', 'title', 'status', 'detail', 'errors' => ['rules']]);
    }

    public function test_v1_wrong_method_is_problem_json_http_error(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->serviceToken(['links:create']))
            ->deleteJson('/api/v1/domains')
            ->assertStatus(405)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson(['type' => 'http-error', 'status' => 405]);
    }

    /**
     * @param  array<int, string>  $abilities
     */
    private function serviceToken(array $abilities): string
    {
        $service = Service::query()->create(['name' => 'V1 Service '.fake()->unique()->word()]);

        return $service->createToken('test', $abilities)->plainTextToken;
    }
}
