<?php

namespace Tests\Feature\Links;

use App\Models\Condition;
use App\Models\Link;
use App\Models\Rule;
use App\Services\Links\Conditions\ConditionContext;
use App\Services\Links\LinkRuleResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Exceptions;
use RuntimeException;
use Tests\TestCase;

/**
 * Core coverage for the rule-resolving engine (docs/08-decisions.md (audit 2026-06) — Phase 4).
 * Exercises LinkRuleResolver directly: priority ordering, condition gating,
 * fallback selection, the no-match case, and the unknown-condition-type guard.
 */
class LinkRuleResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_failed_condition_falls_through_to_the_next_rule(): void
    {
        $link = Link::factory()->create();
        $past = Condition::factory()->timeBefore('2000-01-01T00:00:00+00:00')->create();
        Rule::factory()->for($link)->priority(1)->withCondition($past)->create();
        $fallback = Rule::factory()->for($link)->priority(2)->create();

        $resolved = $this->resolver()->resolve($link, $this->context($link));

        $this->assertSame($fallback->id, $resolved?->id);
    }

    public function test_a_matching_condition_selects_its_rule(): void
    {
        $link = Link::factory()->create();
        $future = Condition::factory()->timeBefore('2030-01-01T00:00:00+00:00')->create();
        $rule = Rule::factory()->for($link)->withCondition($future)->create();

        $resolved = $this->resolver()->resolve($link, $this->context($link));

        $this->assertSame($rule->id, $resolved?->id);
    }

    public function test_an_unknown_condition_type_is_reported_and_skipped(): void
    {
        Exceptions::fake();

        $link = Link::factory()->create();
        $unknown = Condition::factory()->state(['type' => 'no_such_type', 'data' => []])->create();
        Rule::factory()->for($link)->withCondition($unknown)->create();

        $resolved = $this->resolver()->resolve($link, $this->context($link));

        $this->assertNull($resolved);
        Exceptions::assertReported(
            fn (RuntimeException $e): bool => str_contains($e->getMessage(), 'no_such_type'),
        );
    }

    public function test_it_returns_an_unconditional_rule(): void
    {
        $link = Link::factory()->create();
        $rule = Rule::factory()->for($link)->create();

        $resolved = $this->resolver()->resolve($link, $this->context($link));

        $this->assertSame($rule->id, $resolved?->id);
    }

    public function test_it_returns_null_when_no_rule_matches(): void
    {
        $link = Link::factory()->create();
        $past = Condition::factory()->timeBefore('2000-01-01T00:00:00+00:00')->create();
        Rule::factory()->for($link)->withCondition($past)->create();

        $resolved = $this->resolver()->resolve($link, $this->context($link));

        $this->assertNull($resolved);
    }

    public function test_it_returns_the_first_rule_by_priority(): void
    {
        $link = Link::factory()->create();
        $first = Rule::factory()->for($link)->priority(1)->create();
        Rule::factory()->for($link)->priority(2)->create();

        $resolved = $this->resolver()->resolve($link, $this->context($link));

        $this->assertSame($first->id, $resolved?->id);
    }

    private function context(Link $link, string $now = '2026-06-09T12:00:00+00:00'): ConditionContext
    {
        return new ConditionContext($link, Request::create('/'), CarbonImmutable::parse($now));
    }

    private function resolver(): LinkRuleResolver
    {
        return app(LinkRuleResolver::class);
    }
}
