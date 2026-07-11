<?php

namespace Tests\Feature;

use App\Models\Link;
use App\Models\Rule;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stage 1 of docs/07-plans.md — Cache-Control: no-store on resolve responses.
 *
 * A browser-cached redirect (or interstitial) swallows repeat visits: no click
 * is recorded and no callback reaches the partner. Every resolve response must
 * therefore forbid caching outright.
 */
class ResolveLinkNoStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_redirect_carries_no_store(): void
    {
        $code = $this->setupLink(transitionMode: null);

        $response = $this->get(static::SHORT_LINK_HOST.'/'.$code);

        $response->assertRedirect();
        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
        );
    }

    public function test_interstitial_page_carries_no_store(): void
    {
        $code = $this->setupLink(transitionMode: 'delayed');

        $response = $this->get(static::SHORT_LINK_HOST.'/'.$code);

        $response->assertOk();
        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
        );
    }

    public function test_manual_page_carries_no_store(): void
    {
        $code = $this->setupLink(transitionMode: 'manual');

        $response = $this->get(static::SHORT_LINK_HOST.'/'.$code);

        $response->assertOk();
        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
        );
    }

    private function setupLink(?string $transitionMode): string
    {
        $link = Link::factory()->create();

        $code = fake()->unique()->bothify('????####');
        $link->update(['code' => $code]);

        Rule::factory()->create([
            'link_id' => $link->id,
            'url_id' => Url::factory()->create()->id,
            'priority' => 1,
            'transition_mode' => $transitionMode,
        ]);

        return $code;
    }
}
