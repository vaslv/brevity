<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureTechnicalHost;
use App\Models\Link;
use App\Models\Rule;
use App\Models\Service;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every host in APP_HOST resolves to the same app, but the two roles are split:
 * only the technical host (the host of APP_URL — `localhost` under phpunit)
 * serves the admin panel, API and Horizon, and only the short-link domains
 * resolve short codes. Each role 404s on the other's hosts.
 *
 * @see EnsureTechnicalHost
 * @see EnsureShortLinkHost
 */
class TechnicalHostRestrictionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_404s_on_a_short_link_host(): void
    {
        $this->get(self::SHORT_LINK_HOST.'/login')->assertNotFound();
    }

    public function test_admin_login_is_served_on_the_technical_host(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_horizon_404s_on_a_short_link_host(): void
    {
        $this->get(self::SHORT_LINK_HOST.'/horizon')->assertNotFound();
    }

    public function test_short_links_404_on_an_unknown_host_not_in_app_host(): void
    {
        $target = 'https://example.com/landing';
        $code = $this->createDomainlessLink($target);

        // A host pointed at the server but absent from APP_HOST is neither the
        // technical host nor a known short-link domain — the allowlist hides it.
        $this->get('http://unknown.test/'.$code)->assertNotFound();
    }

    public function test_short_links_resolve_on_a_short_link_host_but_404_on_the_technical_host(): void
    {
        $target = 'https://example.com/landing';
        $code = $this->createDomainlessLink($target);

        // Short-link domain: resolves. Technical host: 404 — it serves the admin
        // panel, API and Horizon only and must never redirect a short code.
        // (Both hosts spelled out absolutely: a relative URL after an absolute
        // request inherits the prior request's host in Laravel's test client.)
        $this->get(self::SHORT_LINK_HOST.'/'.$code)->assertRedirect($target);
        $this->get('http://localhost/'.$code)->assertNotFound();
    }

    public function test_the_api_404s_on_a_short_link_host_even_with_a_valid_token(): void
    {
        // The host guard runs before auth/throttle, so an authenticated call to
        // the wrong host is hidden (404), not merely rejected.
        $this->withHeader('Authorization', 'Bearer '.$this->serviceToken())
            ->postJson(self::SHORT_LINK_HOST.'/api/links', ['rules' => [['url' => 'https://example.com/x']]])
            ->assertNotFound();
    }

    public function test_the_api_is_reachable_on_the_technical_host(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->serviceToken())
            ->postJson('/api/links', ['rules' => [['url' => 'https://example.com/x']]])
            ->assertCreated();
    }

    private function createDomainlessLink(string $targetUrl): string
    {
        $service = Service::query()->create(['name' => 'Routing Service '.fake()->unique()->word()]);

        $link = Link::query()->create([
            'service_id' => $service->id,
            'title' => 'Host restriction test',
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

    private function serviceToken(): string
    {
        $service = Service::query()->create(['name' => 'Token Service '.fake()->unique()->word()]);

        return $service->createToken('test', ['links:create'])->plainTextToken;
    }
}
