<?php

namespace Tests\Feature;

use App\Models\Click;
use App\Models\Domain;
use App\Models\IpAddress;
use App\Models\Link;
use App\Models\Referrer;
use App\Models\Rule;
use App\Models\Service;
use App\Models\Url;
use App\Models\UserAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveLinkTransitionModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_accepts_case_insensitive_domain_match(): void
    {
        $targetUrl = 'https://example.com/domain';
        $domain = Domain::query()->create([
            'value' => 'example.com',
        ]);

        $code = $this->createRuleForCode($targetUrl, null, $domain);

        $this->get('http://EXAMPLE.COM/'.$code)
            ->assertRedirect($targetUrl);
    }

    public function test_it_keeps_nullable_dictionaries_empty_when_headers_are_missing(): void
    {
        $targetUrl = 'https://example.com/no-headers';
        $code = $this->createRuleForCode($targetUrl, null);

        $headers = [
            'Referer' => '',
            'User-Agent' => '',
        ];

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])
            ->get('/'.$code, $headers)
            ->assertRedirect($targetUrl);

        $click = Click::query()->firstOrFail();

        $this->assertNull($click->referrer_id);
        $this->assertNull($click->user_agent_id);
        $this->assertNotNull($click->ip_address_id);
        $this->assertSame(0, Referrer::query()->count());
        $this->assertSame(0, UserAgent::query()->count());
        $this->assertSame(1, IpAddress::query()->count());
    }

    public function test_it_records_click_and_reuses_dictionary_rows_for_repeat_transitions(): void
    {
        $targetUrl = 'https://example.com/stats';
        $code = $this->createRuleForCode($targetUrl, null);

        $headers = [
            'Referer' => 'https://source.example/path',
            'User-Agent' => 'Brevity Test Agent',
        ];

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get('/'.$code, $headers)
            ->assertRedirect($targetUrl);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get('/'.$code, $headers)
            ->assertRedirect($targetUrl);

        $this->assertDatabaseHas('referrers', [
            'value' => 'https://source.example/path',
        ]);

        $this->assertDatabaseHas('user_agents', [
            'value' => 'Brevity Test Agent',
        ]);

        $this->assertDatabaseHas('ip_addresses', [
            'value' => '203.0.113.10',
        ]);

        $this->assertSame(1, Referrer::query()->count());
        $this->assertSame(1, UserAgent::query()->count());
        $this->assertSame(1, IpAddress::query()->count());
        $this->assertSame(2, Click::query()->count());
    }

    public function test_it_redirects_using_direct_transition_mode_by_default(): void
    {
        $targetUrl = 'https://example.com/default';
        $code = $this->createRuleForCode($targetUrl, null);

        $this->get('/'.$code)
            ->assertRedirect($targetUrl);
    }

    public function test_it_renders_button_page_when_manual_transition_mode_is_selected(): void
    {
        $targetUrl = 'https://example.com/button';
        $code = $this->createRuleForCode($targetUrl, 'manual');

        $this->get('/'.$code)
            ->assertOk()
            ->assertSee($targetUrl)
            ->assertSee('Continue');
    }

    public function test_it_renders_countdown_page_when_delayed_transition_mode_is_selected(): void
    {
        $targetUrl = 'https://example.com/countdown';
        $code = $this->createRuleForCode($targetUrl, 'delayed');

        $this->get('/'.$code)
            ->assertOk()
            ->assertSee($targetUrl)
            ->assertSee('id="countdown"', false);
    }

    private function createRuleForCode(string $targetUrl, ?string $transitionMode, ?Domain $domain = null): string
    {
        $service = Service::query()->create([
            'name' => 'Resolve Service '.fake()->unique()->word(),
        ]);

        $link = Link::query()->create([
            'service_id' => $service->id,
            'domain_id' => $domain?->id,
            'title' => 'Resolve test link',
            'forward_query' => false,
        ]);

        $code = fake()->unique()->bothify('????####');

        $link->update([
            'code' => $code,
        ]);

        $url = Url::query()->create([
            'value' => $targetUrl,
        ]);

        Rule::query()->create([
            'link_id' => $link->id,
            'url_id' => $url->id,
            'transition_mode' => $transitionMode,
            'priority' => 1,
        ]);

        return $code;
    }
}
