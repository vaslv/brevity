<?php

namespace Tests\Feature\Api;

use App\Models\Link;
use App\Models\LinkClickCounter;
use App\Models\Rule;
use App\Models\Service;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Stage 2 of docs/07-plans.md — GET /api/v1/links/{code}
 * (docs/03-api.md §5.1): the POST-response shape plus lifecycle fields and a
 * click summary from the slot counters. Tenant scope: a token reads only its
 * own service's links; a foreign, unknown or soft-deleted code is a plain 404
 * (existence is never revealed). Requires the links:read ability.
 */
class ShowLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_foreign_link_is_a_plain_404(): void
    {
        $owner = $this->makeService();
        $stranger = $this->makeService();
        $link = $this->makeLink($owner);

        $this->show($stranger, $link->code)
            ->assertStatus(404)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson(['type' => 'not-found']);
    }

    public function test_a_link_without_counters_reports_zero_clicks(): void
    {
        $service = $this->makeService();
        $link = $this->makeLink($service);

        $this->show($service, $link->code)
            ->assertOk()
            ->assertJsonPath('data.clicks.total', 0)
            ->assertJsonPath('data.clicks.non_bots', 0);
    }

    public function test_a_soft_deleted_link_is_404(): void
    {
        $service = $this->makeService();
        $link = $this->makeLink($service);
        $link->delete();

        $this->show($service, $link->code)->assertStatus(404);
    }

    public function test_a_token_without_links_read_is_forbidden(): void
    {
        $service = $this->makeService();
        $link = $this->makeLink($service);
        $token = $service->createToken('legacy', ['links:create'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/links/'.$link->code)
            ->assertStatus(403)
            ->assertJson(['type' => 'missing-ability']);
    }

    public function test_an_unknown_code_is_404(): void
    {
        $this->show($this->makeService(), 'nosuchcode')
            ->assertStatus(404)
            ->assertJson(['type' => 'not-found']);
    }

    public function test_returns_own_link_with_click_summary(): void
    {
        $service = $this->makeService();
        $link = $this->makeLink($service);
        LinkClickCounter::query()->create(['link_id' => $link->id, 'is_bot' => false, 'slot' => 1, 'count' => 7]);
        LinkClickCounter::query()->create(['link_id' => $link->id, 'is_bot' => true, 'slot' => 1, 'count' => 3]);

        $this->show($service, $link->code)
            ->assertOk()
            ->assertJsonPath('data.code', $link->code)
            ->assertJsonPath('data.clicks.total', 10)
            ->assertJsonPath('data.clicks.non_bots', 7)
            ->assertJsonStructure(['data' => [
                'url', 'domain', 'code', 'title', 'forward_query', 'callback_data',
                'valid_since', 'valid_until', 'max_clicks', 'clicks', 'rules',
            ]]);
    }

    private function makeLink(Service $service): Link
    {
        $link = Link::factory()->create(['service_id' => $service->id]);
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
        return Service::query()->create(['name' => 'Show Service '.fake()->unique()->word()]);
    }

    private function show(Service $service, string $code): TestResponse
    {
        $token = $service->createToken('test', ['links:read'])->plainTextToken;

        return $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/links/'.$code);
    }
}
