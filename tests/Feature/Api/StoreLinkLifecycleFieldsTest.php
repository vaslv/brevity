<?php

namespace Tests\Feature\Api;

use App\Models\Link;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Stage 2 of docs/07-plans.md — lifecycle fields on link creation
 * (docs/03-api.md §5): valid_since / valid_until / max_clicks are optional,
 * strict ISO 8601 (same format as condition dates), echoed in the 201 body;
 * a zero-length window is allowed (edges are inclusive on resolve).
 */
class StoreLinkLifecycleFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_lifecycle_fields_are_stored_and_echoed(): void
    {
        $response = $this->createLink([
            'valid_since' => '2026-08-01T00:00:00+00:00',
            'valid_until' => '2026-09-01T00:00:00+00:00',
            'max_clicks' => 100,
            'rules' => [['url' => 'https://example.com/x']],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.valid_since', '2026-08-01T00:00:00+00:00')
            ->assertJsonPath('data.valid_until', '2026-09-01T00:00:00+00:00')
            ->assertJsonPath('data.max_clicks', 100);

        $link = Link::query()->firstOrFail();
        $this->assertSame('2026-08-01T00:00:00+00:00', $link->valid_since?->toIso8601String());
        $this->assertSame(100, $link->max_clicks);
    }

    public function test_loose_date_format_is_rejected(): void
    {
        $this->createLink([
            'valid_until' => '2026-08-01 10:00:00',
            'rules' => [['url' => 'https://example.com/x']],
        ])->assertStatus(422);
    }

    public function test_non_positive_max_clicks_is_rejected(): void
    {
        $this->createLink([
            'max_clicks' => 0,
            'rules' => [['url' => 'https://example.com/x']],
        ])->assertStatus(422);
    }

    public function test_offsets_are_normalized_to_utc_preserving_the_instant(): void
    {
        // Contract: the response speaks UTC; the instant is preserved.
        $this->createLink([
            'valid_since' => '2026-08-01T00:00:00+03:00',
            'rules' => [['url' => 'https://example.com/x']],
        ])->assertCreated()
            ->assertJsonPath('data.valid_since', '2026-07-31T21:00:00+00:00');
    }

    public function test_omitted_lifecycle_fields_are_null_in_the_response(): void
    {
        $this->createLink(['rules' => [['url' => 'https://example.com/x']]])
            ->assertCreated()
            ->assertJsonPath('data.valid_since', null)
            ->assertJsonPath('data.valid_until', null)
            ->assertJsonPath('data.max_clicks', null);
    }

    public function test_until_before_since_is_rejected(): void
    {
        $this->createLink([
            'valid_since' => '2026-09-01T00:00:00+00:00',
            'valid_until' => '2026-08-01T00:00:00+00:00',
            'rules' => [['url' => 'https://example.com/x']],
        ])->assertStatus(422)->assertJsonPath('type', 'validation-error');
    }

    public function test_zero_length_window_is_allowed(): void
    {
        $this->createLink([
            'valid_since' => '2026-08-01T00:00:00+00:00',
            'valid_until' => '2026-08-01T00:00:00+00:00',
            'rules' => [['url' => 'https://example.com/x']],
        ])->assertCreated();
    }

    private function createLink(array $payload): TestResponse
    {
        $service = Service::query()->create(['name' => 'Lifecycle Service '.fake()->unique()->word()]);
        $token = $service->createToken('test', ['links:create'])->plainTextToken;

        return $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/links', $payload);
    }
}
