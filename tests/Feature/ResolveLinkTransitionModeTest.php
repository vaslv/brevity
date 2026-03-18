<?php

namespace Tests\Feature;

use App\Models\Link;
use App\Models\Rule;
use App\Models\Service;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveLinkTransitionModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_redirects_using_direct_transition_mode_by_default(): void
    {
        $targetUrl = 'https://example.com/default';
        $code = $this->createRuleForCode($targetUrl, null);

        $this->get('/'.$code)
            ->assertRedirect($targetUrl);
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

    public function test_it_renders_button_page_when_manual_transition_mode_is_selected(): void
    {
        $targetUrl = 'https://example.com/button';
        $code = $this->createRuleForCode($targetUrl, 'manual');

        $this->get('/'.$code)
            ->assertOk()
            ->assertSee($targetUrl)
            ->assertSee('Continue');
    }

    /**
     * @param  string|null  $transitionMode
     */
    private function createRuleForCode(string $targetUrl, ?string $transitionMode): string
    {
        $service = Service::query()->create([
            'name' => 'Resolve Service '.fake()->unique()->word(),
        ]);

        $link = Link::query()->create([
            'service_id' => $service->id,
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
