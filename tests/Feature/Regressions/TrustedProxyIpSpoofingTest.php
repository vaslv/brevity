<?php

namespace Tests\Feature\Regressions;

use App\Models\Link;
use App\Models\Rule;
use App\Models\Service;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression for docs/CODE_REVIEW.md — C1 (Critical).
 *
 * `bootstrap/app.php` calls `trustProxies(at: '*')`, so every client is treated
 * as a trusted proxy and a forged `X-Forwarded-For` is taken as the client IP.
 * That makes the recorded click IP (and the `{{click.ip}}` callback variable)
 * attacker-controlled and lets an attacker rotate IPs to bypass the per-IP
 * `link-resolve` rate limiter.
 *
 * A direct request from an UNtrusted (public) address must ignore
 * `X-Forwarded-For` and record the real `REMOTE_ADDR`.
 *
 * Fixed: `bootstrap/app.php` now trusts only private proxy subnets instead of
 * '*'. This test guards against regressing back to a permissive proxy config.
 */
class TrustedProxyIpSpoofingTest extends TestCase
{
    use RefreshDatabase;

    public function test_forged_x_forwarded_for_from_untrusted_client_is_ignored(): void
    {
        $realIp = '198.51.100.7';   // public TEST-NET-2 — not a trusted proxy
        $spoofedIp = '203.0.113.99'; // attacker-supplied X-Forwarded-For

        $code = $this->createLink('https://example.com/landing');

        // QUEUE_CONNECTION=sync under phpunit.xml, so the click is recorded inline.
        $this->withServerVariables([
            'REMOTE_ADDR' => $realIp,
            'HTTP_X_FORWARDED_FOR' => $spoofedIp,
        ])->get('/'.$code)->assertRedirect();

        $this->assertDatabaseHas('ip_addresses', ['value' => $realIp]);
        $this->assertDatabaseMissing('ip_addresses', ['value' => $spoofedIp]);
    }

    private function createLink(string $targetUrl): string
    {
        $service = Service::query()->create([
            'name' => 'Proxy Service '.fake()->unique()->word(),
        ]);

        $link = Link::query()->create([
            'service_id' => $service->id,
            'title' => 'Proxy spoofing test',
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
