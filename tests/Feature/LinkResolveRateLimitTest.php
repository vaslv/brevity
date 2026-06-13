<?php

namespace Tests\Feature;

use App\Models\Link;
use App\Models\Rule;
use App\Models\Service;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LinkResolveRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_applies_rate_limit_per_code_not_across_all_links(): void
    {
        $codeA = $this->createLink();
        $codeB = $this->createLink();

        // Burn the limit for code A.
        for ($i = 0; $i < 8; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.70'])
                ->get(static::SHORT_LINK_HOST.'/'.$codeA);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.70'])
            ->get(static::SHORT_LINK_HOST.'/'.$codeA)
            ->assertStatus(429);

        // Code B from same IP should still resolve (per-code counter is separate).
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.70'])
            ->get(static::SHORT_LINK_HOST.'/'.$codeB)
            ->assertRedirect();
    }

    public function test_it_applies_rate_limit_per_ip_not_globally(): void
    {
        $code = $this->createLink();

        // Burn the per-link limit for one IP.
        for ($i = 0; $i < 8; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.60'])
                ->get(static::SHORT_LINK_HOST.'/'.$code);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.60'])
            ->get(static::SHORT_LINK_HOST.'/'.$code)
            ->assertStatus(429);

        // Different IP must not be affected.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.61'])
            ->get(static::SHORT_LINK_HOST.'/'.$code)
            ->assertRedirect();
    }

    public function test_it_throttles_repeated_hits_on_the_same_link_from_same_ip(): void
    {
        $code = $this->createLink();

        // Per-link/IP limit is 8/min. First 8 pass, 9th is 429.
        for ($i = 0; $i < 8; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
                ->get(static::SHORT_LINK_HOST.'/'.$code)
                ->assertRedirect();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
            ->get(static::SHORT_LINK_HOST.'/'.$code)
            ->assertStatus(429);
    }

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('link-resolve');
    }

    private function createLink(): string
    {
        $service = Service::query()->create([
            'name' => 'Throttle Service '.fake()->unique()->word(),
        ]);

        $link = Link::query()->create([
            'service_id' => $service->id,
            'title' => 'Throttle test',
            'forward_query' => false,
        ]);

        $code = fake()->unique()->bothify('????####');
        $link->update(['code' => $code]);

        $url = Url::query()->create(['value' => 'https://target.example/'.fake()->unique()->slug()]);

        Rule::query()->create([
            'link_id' => $link->id,
            'url_id' => $url->id,
            'priority' => 1,
        ]);

        return $code;
    }
}
