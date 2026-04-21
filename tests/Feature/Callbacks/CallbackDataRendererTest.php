<?php

namespace Tests\Feature\Callbacks;

use App\Models\Click;
use App\Models\IpAddress;
use App\Models\Link;
use App\Models\Referrer;
use App\Models\Service;
use App\Models\Url;
use App\Models\UserAgent;
use App\Services\Links\Callbacks\CallbackDataRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallbackDataRendererTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_re_substitute_values_containing_placeholders(): void
    {
        // Attack: visitor sets Referer to "{{click.user_agent}}" and UA to a secret.
        // Without the single-pass fix, referrer placeholder would be replaced with
        // "{{click.user_agent}}", which would then be resolved in a later iteration.
        $click = $this->makeClick(
            referrer: '{{click.user_agent}}',
            userAgent: 'SECRET',
        );

        $rendered = app(CallbackDataRenderer::class)->render([
            'ref' => '{{click.referrer}}',
        ], $click);

        $this->assertSame('{{click.user_agent}}', $rendered['ref']);
        $this->assertStringNotContainsString('SECRET', $rendered['ref']);
    }

    public function test_it_leaves_unknown_placeholders_untouched(): void
    {
        $click = $this->makeClick();

        $rendered = app(CallbackDataRenderer::class)->render([
            'x' => '{{nonexistent.var}}',
        ], $click);

        $this->assertSame('{{nonexistent.var}}', $rendered['x']);
    }

    public function test_it_preserves_non_string_scalar_values(): void
    {
        $click = $this->makeClick();

        $rendered = app(CallbackDataRenderer::class)->render([
            'int' => 42,
            'bool' => true,
            'null' => null,
            'float' => 3.14,
        ], $click);

        $this->assertSame(42, $rendered['int']);
        $this->assertTrue($rendered['bool']);
        $this->assertNull($rendered['null']);
        $this->assertSame(3.14, $rendered['float']);
    }

    public function test_it_renders_variables_recursively_in_nested_arrays(): void
    {
        $click = $this->makeClick();

        $rendered = app(CallbackDataRenderer::class)->render([
            'outer' => [
                'nested' => '{{click.id}}',
                'list' => ['{{click.id}}', 'static'],
            ],
        ], $click);

        $this->assertSame((string) $click->id, $rendered['outer']['nested']);
        $this->assertSame((string) $click->id, $rendered['outer']['list'][0]);
        $this->assertSame('static', $rendered['outer']['list'][1]);
    }

    public function test_it_substitutes_known_variables(): void
    {
        $click = $this->makeClick(
            ip: '203.0.113.10',
            referrer: 'https://source.example/path',
            userAgent: 'Mozilla/5.0',
            url: 'https://target.example',
            code: 'AbC12345',
            title: 'Campaign 42',
        );

        $rendered = app(CallbackDataRenderer::class)->render([
            'id' => '{{click.id}}',
            'ip' => '{{click.ip}}',
            'ref' => '{{click.referrer}}',
            'ua' => '{{click.user_agent}}',
            'code' => '{{link.code}}',
            'title' => '{{link.title}}',
            'url' => '{{click.url}}',
        ], $click);

        $this->assertSame((string) $click->id, $rendered['id']);
        $this->assertSame('203.0.113.10', $rendered['ip']);
        $this->assertSame('https://source.example/path', $rendered['ref']);
        $this->assertSame('Mozilla/5.0', $rendered['ua']);
        $this->assertSame('AbC12345', $rendered['code']);
        $this->assertSame('Campaign 42', $rendered['title']);
        $this->assertSame('https://target.example', $rendered['url']);
    }

    private function makeClick(
        ?string $ip = '203.0.113.1',
        ?string $referrer = null,
        ?string $userAgent = null,
        string $url = 'https://target.example/default',
        string $code = 'TstC1234',
        ?string $title = null,
    ): Click {
        $service = Service::query()->create(['name' => 'Renderer Test '.fake()->unique()->word()]);

        $link = Link::query()->create([
            'service_id' => $service->id,
            'title' => $title,
            'forward_query' => false,
        ]);
        $link->update(['code' => $code]);

        $urlModel = Url::query()->create(['value' => $url]);

        $ipId = $ip ? IpAddress::query()->create(['value' => $ip])->id : null;
        $referrerId = $referrer !== null ? Referrer::query()->create(['value' => $referrer])->id : null;
        $userAgentId = $userAgent !== null ? UserAgent::query()->create(['value' => $userAgent])->id : null;

        return Click::query()->create([
            'service_id' => $service->id,
            'link_id' => $link->id,
            'url_id' => $urlModel->id,
            'ip_address_id' => $ipId,
            'referrer_id' => $referrerId,
            'user_agent_id' => $userAgentId,
        ])->fresh(['link', 'url', 'ipAddress', 'referrer', 'userAgent']);
    }
}
