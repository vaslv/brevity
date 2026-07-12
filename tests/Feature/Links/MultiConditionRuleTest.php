<?php

namespace Tests\Feature\Links;

use App\Models\Condition;
use App\Models\Link;
use App\Models\Rule;
use App\Models\Service;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Stage 3 of docs/07-plans.md — multi-condition rules (RUL-01).
 *
 * A rule matches only when ALL of its conditions match (AND); a rule with no
 * conditions is unconditional. The API accepts a `conditions` list and still
 * accepts the legacy single `condition` for backward compatibility.
 */
class MultiConditionRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_rule_matches_only_when_all_conditions_match(): void
    {
        // Two time_before conditions: the earlier one gates the rule.
        $future = Condition::query()->create(['type' => 'time_before', 'data' => ['before' => now()->addDay()->toIso8601String()]]);
        $past = Condition::query()->create(['type' => 'time_before', 'data' => ['before' => now()->subDay()->toIso8601String()]]);

        $bothFuture = $this->linkWithRules([
            ['url' => 'https://example.com/matched', 'conditions' => [$future, $future]],
            ['url' => 'https://example.com/fallback', 'conditions' => []],
        ]);
        $this->get(static::SHORT_LINK_HOST.'/'.$bothFuture)
            ->assertRedirect('https://example.com/matched');

        // One condition already expired → the AND-rule fails, fallback wins.
        $onePast = $this->linkWithRules([
            ['url' => 'https://example.com/matched', 'conditions' => [$future, $past]],
            ['url' => 'https://example.com/fallback', 'conditions' => []],
        ]);
        $this->get(static::SHORT_LINK_HOST.'/'.$onePast)
            ->assertRedirect('https://example.com/fallback');
    }

    public function test_api_accepts_a_conditions_list(): void
    {
        $response = $this->createLink([
            'rules' => [[
                'url' => 'https://example.com/x',
                'conditions' => [
                    ['type' => 'time_before', 'data' => ['before' => '2026-03-05T10:00:00+00:00']],
                    ['type' => 'time_before', 'data' => ['before' => '2026-04-05T10:00:00+00:00']],
                ],
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonCount(2, 'data.rules.0.conditions');

        $this->assertSame(2, Rule::query()->firstOrFail()->conditions()->count());
    }

    public function test_api_still_accepts_the_legacy_single_condition(): void
    {
        $response = $this->createLink([
            'rules' => [[
                'url' => 'https://example.com/x',
                'condition' => ['type' => 'time_before', 'data' => ['before' => '2026-03-05T10:00:00+00:00']],
            ]],
        ]);

        $response->assertCreated()
            // Folded into the canonical list...
            ->assertJsonCount(1, 'data.rules.0.conditions')
            // ...and still echoed under the deprecated singular field.
            ->assertJsonPath('data.rules.0.condition.type', 'time_before');
    }

    public function test_duplicate_conditions_on_one_rule_collapse(): void
    {
        $condition = Condition::query()->create(['type' => 'time_before', 'data' => ['before' => now()->addDay()->toIso8601String()]]);

        $code = $this->linkWithRules([
            ['url' => 'https://example.com/x', 'conditions' => [$condition, $condition]],
        ]);

        $rule = Link::query()->where('code', $code)->firstOrFail()->rules()->firstOrFail();
        $this->assertSame(1, $rule->conditions()->count());
    }

    private function createLink(array $payload): TestResponse
    {
        $service = Service::query()->create(['name' => 'MC Service '.fake()->unique()->word()]);
        $token = $service->createToken('test', ['links:create'])->plainTextToken;

        return $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/links', $payload);
    }

    /**
     * @param  array<int, array{url: string, conditions: array<int, Condition>}>  $ruleSpecs
     */
    private function linkWithRules(array $ruleSpecs): string
    {
        $link = Link::factory()->create();
        $code = fake()->unique()->bothify('????####');
        $link->update(['code' => $code]);

        foreach ($ruleSpecs as $index => $spec) {
            $rule = Rule::factory()->create([
                'link_id' => $link->id,
                'url_id' => Url::query()->firstOrCreate(['value' => $spec['url']])->id,
                'priority' => $index + 1,
            ]);
            $rule->conditions()->attach(collect($spec['conditions'])->pluck('id')->unique());
        }

        return $code;
    }
}
