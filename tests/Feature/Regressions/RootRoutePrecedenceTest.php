<?php

namespace Tests\Feature\Regressions;

use App\Models\Link;
use App\Models\Rule;
use App\Models\Service;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guard for docs/08-decisions.md (review 2026-06) — M5.
 *
 * The root catch-all `GET /{code}` shares the root namespace with the Filament
 * panel (path '') and the `/up` health check. This test locks in that the
 * catch-all does NOT shadow those reserved paths (Filament/health routes are
 * registered first and win), while real short codes still resolve on a
 * short-link host. It also pins the code constraint so junk paths 404 at the
 * router. The catch-all is host-scoped to short-link domains
 * (see EnsureShortLinkHost), so the panel/health checks run on the technical
 * host without ever colliding with the resolver.
 */
class RootRoutePrecedenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_short_code_still_resolves(): void
    {
        $target = 'https://example.com/landing';
        $code = $this->createLink($target);

        $this->get(static::SHORT_LINK_HOST.'/'.$code)->assertRedirect($target);
    }

    public function test_filament_login_is_not_shadowed_by_the_code_resolver(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_health_check_is_not_shadowed_by_the_code_resolver(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_junk_single_segment_paths_404_at_the_router(): void
    {
        // Dotted / wrong-length paths don't match the code constraint, so they
        // never reach the resolver or the rate limiter — checked on a short-link
        // host where the resolver route actually exists.
        $this->get(static::SHORT_LINK_HOST.'/favicon.ico')->assertNotFound();
        $this->get(static::SHORT_LINK_HOST.'/ab')->assertNotFound();
    }

    private function createLink(string $targetUrl): string
    {
        $service = Service::query()->create([
            'name' => 'Routing Service '.fake()->unique()->word(),
        ]);

        $link = Link::query()->create([
            'service_id' => $service->id,
            'title' => 'Routing test',
            'forward_query' => false,
        ]);

        $code = fake()->unique()->bothify('????####');
        $link->update(['code' => $code]);

        $url = Url::query()->create(['value' => $targetUrl]);

        Rule::query()->create([
            'link_id' => $link->id,
            'url_id' => $url->id,
            'priority' => 1,
        ]);

        return $code;
    }
}
