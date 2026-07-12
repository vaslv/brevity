<?php

namespace Tests\Feature\Links;

use App\Models\Callback;
use App\Models\Click;
use App\Models\Link;
use App\Models\Rule;
use App\Models\RuleVariant;
use App\Models\Service;
use App\Models\Url;
use App\Services\Links\Clicks\ClickRecorder;
use App\Services\Links\RuleVariantSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Stage 3 of docs/07-plans.md — A/B split (GAP-04). A winning rule with
 * variants routes to a weighted target, sticky per (ip, ua, link). The click
 * records which variant it hit and {{click.variant}} carries the label to the
 * callback. A rule without variants behaves exactly as before.
 */
class RuleVariantSplitTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_click_survives_a_variant_deleted_before_the_job_runs(): void
    {
        // Simulate a PATCH that dropped the chosen variant between the redirect
        // and the async click recorder: the click must still be recorded, with
        // a null variant instead of an FK violation.
        $link = Link::factory()->create();
        $url = Url::factory()->create();
        $rule = Rule::factory()->create(['link_id' => $link->id, 'url_id' => $url->id]);
        $variant = RuleVariant::factory()->create(['rule_id' => $rule->id, 'url_id' => $url->id, 'weight' => 1]);

        $goneVariantId = $variant->id;
        $variant->delete();

        $click = app(ClickRecorder::class)->record(
            $link,
            (string) Str::uuid(),
            $url->id,
            '203.0.113.9',
            null,
            'UA',
            null,
            $goneVariantId,
        );

        $this->assertNull($click->rule_variant_id);
        $this->assertSame(1, Click::query()->count());
    }

    public function test_a_rule_without_variants_uses_its_own_url(): void
    {
        $link = Link::factory()->create();
        $rule = Rule::factory()->create([
            'link_id' => $link->id,
            'url_id' => Url::factory()->create(['value' => 'https://example.com/plain'])->id,
        ]);

        $result = app(RuleVariantSelector::class)->select($rule->load('url', 'variants.url'), $link, Request::create('/'));

        $this->assertNull($result['variant']);
        $this->assertSame('https://example.com/plain', $result['url_value']);
    }

    public function test_resolving_records_the_variant_and_labels_the_callback(): void
    {
        Http::fake();

        $service = Service::factory()->create(['callback_url' => 'https://93.184.216.34/hook']);
        $link = Link::factory()->create([
            'service_id' => $service->id,
            'callback_data' => ['arm' => '{{click.variant}}'],
        ]);
        $code = fake()->unique()->bothify('????####');
        $link->update(['code' => $code]);

        $rule = Rule::factory()->create([
            'link_id' => $link->id,
            'url_id' => Url::factory()->create(['value' => 'https://example.com/control'])->id,
        ]);
        // Single-variant weights forced so the outcome is deterministic here.
        RuleVariant::factory()->create([
            'rule_id' => $rule->id,
            'url_id' => Url::factory()->create(['value' => 'https://example.com/only'])->id,
            'weight' => 1,
            'label' => 'winner',
        ]);

        $this->get(static::SHORT_LINK_HOST.'/'.$code)
            ->assertRedirect('https://example.com/only');

        $click = Click::query()->firstOrFail();
        $this->assertNotNull($click->rule_variant_id);
        $this->assertSame('winner', Callback::query()->firstOrFail()->data['arm']);
    }

    public function test_the_choice_is_sticky_for_the_same_visitor(): void
    {
        [$rule, $link] = $this->ruleWithVariants(['https://example.com/a' => 1, 'https://example.com/b' => 1]);
        $selector = app(RuleVariantSelector::class);
        $request = Request::create('/', server: ['REMOTE_ADDR' => '203.0.113.5', 'HTTP_USER_AGENT' => 'Mozilla/5.0']);

        $first = $selector->select($rule, $link, $request)['url_value'];

        // Same visitor, many attempts — always the same variant.
        for ($i = 0; $i < 10; $i++) {
            $this->assertSame($first, $selector->select($rule, $link, $request)['url_value']);
        }
    }

    public function test_traffic_splits_roughly_by_weight(): void
    {
        // 1:9 weights — B should dominate across many distinct visitors.
        [$rule, $link] = $this->ruleWithVariants(['https://example.com/a' => 1, 'https://example.com/b' => 9]);
        $selector = app(RuleVariantSelector::class);

        $counts = ['https://example.com/a' => 0, 'https://example.com/b' => 0];
        for ($i = 0; $i < 200; $i++) {
            $request = Request::create('/', server: ['REMOTE_ADDR' => "10.0.{$i}.1", 'HTTP_USER_AGENT' => 'UA']);
            $counts[$selector->select($rule, $link, $request)['url_value']]++;
        }

        // Not asserting exact ratios (hash distribution) — just that both arms
        // are hit and the heavier one wins clearly.
        $this->assertGreaterThan(0, $counts['https://example.com/a']);
        $this->assertGreaterThan($counts['https://example.com/a'], $counts['https://example.com/b']);
    }

    /**
     * @param  array<string, int>  $weightedUrls
     * @return array{0: Rule, 1: Link}
     */
    private function ruleWithVariants(array $weightedUrls): array
    {
        $link = Link::factory()->create();
        $rule = Rule::factory()->create([
            'link_id' => $link->id,
            'url_id' => Url::factory()->create()->id,
        ]);

        foreach ($weightedUrls as $url => $weight) {
            RuleVariant::factory()->create([
                'rule_id' => $rule->id,
                'url_id' => Url::query()->firstOrCreate(['value' => $url])->id,
                'weight' => $weight,
            ]);
        }

        return [$rule->load('url', 'variants.url'), $link];
    }
}
