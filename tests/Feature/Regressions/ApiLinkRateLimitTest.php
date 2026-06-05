<?php

namespace Tests\Feature\Regressions;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression for docs/AUDIT_2026-06.md — H8.
 *
 * POST /api/links had no throttle, so a single links:create token could create
 * unbounded links/rules/urls/conditions (storage-amplification DoS). The route
 * is now rate-limited per owning service (120/min).
 */
class ApiLinkRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_link_creation_is_throttled_per_service(): void
    {
        $service = Service::query()->create(['name' => 'Svc '.fake()->unique()->word()]);
        $token = $service->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/links', ['rules' => [['url' => 'https://example.com/a']]])
            ->assertCreated()
            ->assertHeader('X-RateLimit-Limit', 120)
            ->assertHeader('X-RateLimit-Remaining', 119);

        // Same service: the per-service counter decrements across requests.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/links', ['rules' => [['url' => 'https://example.com/b']]])
            ->assertCreated()
            ->assertHeader('X-RateLimit-Remaining', 118);
    }
}
