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
 * Every host in APP_HOST resolves to the same app, but only the technical host
 * (the host of APP_URL — `localhost` under phpunit) may serve the admin panel,
 * API and Horizon. Short-link domains 404 those subsystems while still
 * resolving short codes.
 *
 * @see EnsureTechnicalHost
 */
class TechnicalHostRestrictionTest extends TestCase
{
    use RefreshDatabase;

    private const string SHORT_LINK_HOST = 'http://lnk.test';

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

    public function test_short_links_resolve_on_both_the_technical_and_short_link_hosts(): void
    {
        $target = 'https://example.com/landing';
        $code = $this->createDomainlessLink($target);

        $this->get('/'.$code)->assertRedirect($target);
        $this->get(self::SHORT_LINK_HOST.'/'.$code)->assertRedirect($target);
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
