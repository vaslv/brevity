<?php

namespace Tests\Unit\Conditions;

use App\Models\Condition;
use App\Models\Link;
use App\Services\Links\Conditions\ConditionContext;
use App\Services\Links\Conditions\QueryParamConditionHandler;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Stage 3 of docs/07-plans.md — the query_param condition: matches an exact
 * key=value pair in the visit's query string. Fails closed on malformed data;
 * array and absent params never match a scalar value.
 */
class QueryParamConditionHandlerTest extends TestCase
{
    public function test_a_bare_param_matches_an_empty_expected_value(): void
    {
        // ?flag parses to an empty string. The handler compares strictly, so it
        // matches only when the stored value is also empty (validation forbids
        // storing that, but the match behaviour is pinned here regardless).
        $this->assertTrue($this->matchResult('flag', '', '/?flag'));
        $this->assertFalse($this->matchResult('flag', 'x', '/?flag'));
    }

    public function test_an_absent_param_does_not_match(): void
    {
        $this->assertFalse($this->matchResult('partner', 'acme', '/?utm_source=tg'));
    }

    public function test_an_array_param_does_not_match_a_scalar(): void
    {
        $this->assertFalse($this->matchResult('partner', 'acme', '/?partner[]=acme'));
    }

    public function test_it_does_not_match_a_different_value(): void
    {
        $this->assertFalse($this->matchResult('partner', 'acme', '/?partner=other'));
    }

    public function test_it_fails_closed_on_malformed_data(): void
    {
        $this->assertFalse($this->matchResult(null, 'acme', '/?partner=acme'));
        $this->assertFalse($this->matchResult('partner', null, '/?partner=acme'));
    }

    public function test_it_matches_an_exact_key_value_pair(): void
    {
        $this->assertTrue($this->matchResult('partner', 'acme', '/?partner=acme'));
    }

    public function test_the_type_slug_is_derived_from_the_class_name(): void
    {
        $this->assertSame('query_param', QueryParamConditionHandler::type());
    }

    private function matchResult(?string $key, ?string $value, string $uri): bool
    {
        $condition = new Condition(['type' => 'query_param', 'data' => ['key' => $key, 'value' => $value]]);
        $context = new ConditionContext(
            new Link,
            Request::create($uri),
            CarbonImmutable::now(),
        );

        return (new QueryParamConditionHandler)->matches($condition, $context);
    }
}
