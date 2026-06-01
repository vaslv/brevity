<?php

namespace Tests\Feature\Api;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StoreLinkRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_accepts_delayed_transition_mode(): void
    {
        $response = $this->postLink([
            'rules' => [
                [
                    'url' => 'https://example.com/redirect',
                    'transition_mode' => 'delayed',
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonMissingValidationErrors(['rules.0.transition_mode']);
    }

    public function test_it_accepts_multiple_rules_with_valid_time_before_dates(): void
    {
        $response = $this->postLink([
            'rules' => [
                [
                    'url' => 'https://example.com/redirect1',
                    'condition' => [
                        'type' => 'time_before',
                        'data' => [
                            'before' => '2026-03-05T10:00:00+00:00',
                        ],
                    ],
                ],
                [
                    'url' => 'https://example.com/redirect2',
                    'condition' => [
                        'type' => 'time_before',
                        'data' => [
                            'before' => '2026-05-05T10:00:00+00:00',
                        ],
                    ],
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonMissingValidationErrors([
                'rules.0.condition.data.before',
                'rules.1.condition.data.before',
            ]);
    }

    public function test_it_accepts_valid_iso8601_date_in_time_before_condition(): void
    {
        $response = $this->postLink([
            'rules' => [
                [
                    'url' => 'https://example.com/redirect',
                    'condition' => [
                        'type' => 'time_before',
                        'data' => [
                            'before' => '2026-03-05T10:00:00+00:00',
                        ],
                    ],
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonMissingValidationErrors(['rules.0.condition.data.before']);
    }

    public function test_it_rejects_invalid_before_date_format_in_time_before_condition(): void
    {
        $response = $this->postLink([
            'rules' => [
                [
                    'url' => 'https://example.com/redirect',
                    'condition' => [
                        'type' => 'time_before',
                        'data' => [
                            'before' => '2026-03-05 10:00:00',
                        ],
                    ],
                ],
            ],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rules.0.condition.data.before']);
    }

    public function test_it_rejects_missing_before_in_time_before_condition(): void
    {
        $response = $this->postLink([
            'rules' => [
                [
                    'url' => 'https://example.com/redirect',
                    'condition' => [
                        'type' => 'time_before',
                        'data' => [],
                    ],
                ],
            ],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rules.0.condition.data.before']);
    }

    public function test_it_rejects_non_http_scheme_urls(): void
    {
        // Hardening (CODE_REVIEW m2): only http/https targets are accepted, so a
        // non-web scheme can never be stored as a redirect target.
        $response = $this->postLink([
            'rules' => [
                ['url' => 'ftp://example.com/file'],
            ],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rules.0.url']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function postLink(array $data): TestResponse
    {
        return $this
            ->withServiceToken()
            ->postJson('/api/links', $data);
    }

    private function withServiceToken(): self
    {
        $service = Service::query()->create([
            'name' => 'Service '.fake()->unique()->word(),
        ]);

        $token = $service->createToken('test')->plainTextToken;

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
