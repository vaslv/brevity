<?php

namespace Tests\Unit\Conditions;

use App\Models\Condition;
use App\Models\Link;
use App\Services\Links\Conditions\AfterDateConditionHandler;
use App\Services\Links\Conditions\ConditionContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Stage 3 of docs/07-plans.md — the after_date condition (mirror of
 * time_before). Matches once the current moment is AT or after the stored
 * date (inclusive); fails closed on malformed data.
 */
class AfterDateConditionHandlerTest extends TestCase
{
    public function test_it_does_not_match_before_the_moment(): void
    {
        $this->assertFalse($this->matchResult(
            after: '2026-06-01T00:00:00+00:00',
            now: '2026-01-01T00:00:00+00:00',
        ));
    }

    public function test_it_fails_closed_on_malformed_data(): void
    {
        $this->assertFalse($this->matchResult(after: null, now: '2026-06-01T00:00:00+00:00'));
        $this->assertFalse($this->matchResult(after: 'not-a-date', now: '2026-06-01T00:00:00+00:00'));
    }

    public function test_it_matches_once_the_moment_has_arrived(): void
    {
        $this->assertTrue($this->matchResult(
            after: '2026-01-01T00:00:00+00:00',
            now: '2026-06-01T00:00:00+00:00',
        ));
    }

    public function test_the_edge_is_inclusive(): void
    {
        $this->assertTrue($this->matchResult(
            after: '2026-06-01T00:00:00+00:00',
            now: '2026-06-01T00:00:00+00:00',
        ));
    }

    public function test_the_type_slug_is_derived_from_the_class_name(): void
    {
        $this->assertSame('after_date', AfterDateConditionHandler::type());
    }

    private function matchResult(?string $after, string $now): bool
    {
        $condition = new Condition(['type' => 'after_date', 'data' => ['after' => $after]]);
        $context = new ConditionContext(
            new Link,
            Request::create('/'),
            CarbonImmutable::parse($now),
        );

        return (new AfterDateConditionHandler)->matches($condition, $context);
    }
}
