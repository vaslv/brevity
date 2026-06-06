<?php

namespace Tests\Unit\Conditions;

use App\Models\Condition;
use App\Models\Link;
use App\Services\Links\Conditions\ConditionContext;
use App\Services\Links\Conditions\TimeBeforeConditionHandler;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Coverage for docs/AUDIT_2026-06.md — M14 (and the T7 gap).
 *
 * matches() now reads the stored `before` value directly and fails closed on
 * malformed data, rather than re-running Validator (and reporting to Sentry) on
 * every resolve.
 */
class TimeBeforeConditionHandlerTest extends TestCase
{
    public function test_it_does_not_match_when_the_threshold_has_passed(): void
    {
        $this->assertFalse($this->matchResult(
            before: '2026-01-01T00:00:00+00:00',
            now: '2026-06-06T00:00:00+00:00',
        ));
    }

    public function test_it_fails_closed_on_missing_or_malformed_data(): void
    {
        $handler = new TimeBeforeConditionHandler;
        $context = $this->context('2026-06-06T00:00:00+00:00');

        $this->assertFalse($handler->matches($this->condition([]), $context));
        $this->assertFalse($handler->matches($this->condition(['before' => 'not-a-date']), $context));
        $this->assertFalse($handler->matches($this->condition(['before' => 123]), $context));
    }

    public function test_it_matches_when_now_is_before_the_threshold(): void
    {
        $this->assertTrue($this->matchResult(
            before: '2026-12-31T23:59:59+00:00',
            now: '2026-06-06T00:00:00+00:00',
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function condition(array $data): Condition
    {
        $condition = new Condition;
        $condition->data = $data;

        return $condition;
    }

    private function context(string $now): ConditionContext
    {
        return new ConditionContext(new Link, Request::create('/'), CarbonImmutable::parse($now));
    }

    private function matchResult(string $before, string $now): bool
    {
        return (new TimeBeforeConditionHandler)->matches(
            $this->condition(['before' => $before]),
            $this->context($now),
        );
    }
}
