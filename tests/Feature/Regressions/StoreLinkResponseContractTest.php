<?php

namespace Tests\Feature\Regressions;

use App\Models\Domain;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression for docs/AUDIT_2026-06.md — H1 (and the T10 coverage gap).
 *
 * RuleResource serialized `condition` via ConditionResource::make($condition)
 * with no null guard, so a rule WITHOUT a condition was emitted as
 * {"type":null,"data":null} instead of the `null` that SDK_API.md guarantees.
 * This pins the full POST /api/links 201 response contract, including the
 * condition:null case.
 */
class StoreLinkResponseContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_link_201_response_matches_the_documented_contract(): void
    {
        Domain::query()->create(['value' => 'short.example.com']);

        $service = Service::query()->create(['name' => 'Svc '.fake()->unique()->word()]);
        $token = $service->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/links', [
                'domain' => 'short.example.com',
                'title' => 'Campaign link',
                'forward_query' => true,
                'callback_data' => ['campaign_id' => 'cmp-42'],
                'rules' => [
                    [
                        'url' => 'https://example.com/landing',
                        'condition' => [
                            'type' => 'time_before',
                            'data' => ['before' => '2026-03-05T10:00:00+00:00'],
                        ],
                        'transition_mode' => 'delayed',
                    ],
                    [
                        'url' => 'https://example.com/fallback',
                    ],
                ],
            ]);

        $response->assertCreated();

        $code = $response->json('data.code');
        $this->assertIsString($code);
        $this->assertNotSame('', $code);

        $response
            ->assertJsonStructure([
                'data' => [
                    'url',
                    'domain',
                    'code',
                    'title',
                    'forward_query',
                    'callback_data',
                    'rules' => [
                        ['url', 'condition', 'transition_mode'],
                    ],
                ],
            ])
            ->assertJsonPath('data.url', 'https://short.example.com/'.$code)
            ->assertJsonPath('data.domain', 'short.example.com')
            ->assertJsonPath('data.title', 'Campaign link')
            ->assertJsonPath('data.forward_query', true)
            ->assertJsonPath('data.callback_data', ['campaign_id' => 'cmp-42'])
            ->assertJsonCount(2, 'data.rules')
            // Rule WITH a condition serializes as an object.
            ->assertJsonPath('data.rules.0.url', 'https://example.com/landing')
            ->assertJsonPath('data.rules.0.condition.type', 'time_before')
            ->assertJsonPath('data.rules.0.condition.data', ['before' => '2026-03-05T10:00:00+00:00'])
            ->assertJsonPath('data.rules.0.transition_mode', 'delayed')
            // H1: rule WITHOUT a condition serializes as null (not {type:null,data:null}),
            // and transition_mode is null when not supplied (meaning direct).
            ->assertJsonPath('data.rules.1.url', 'https://example.com/fallback')
            ->assertJsonPath('data.rules.1.condition', null)
            ->assertJsonPath('data.rules.1.transition_mode', null);
    }
}
