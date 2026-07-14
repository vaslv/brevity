<?php

namespace Tests\Unit\Links;

use App\Models\Link;
use App\Models\Rule;
use App\Models\RuleVariant;
use App\Models\Url;
use App\Services\Links\RuleVariantSelector;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Regression for docs/07-plans.md — r47.
 *
 * A rule whose variant weights sum to zero would hit `crc32($key) % 0` and 500
 * the redirect hot path. It is unreachable through the API (weight >= 1 plus a
 * DB CHECK), but an admin/raw-SQL weight of 0 must fail closed to the rule's own
 * url. Built in memory so the zero weight isn't blocked by the DB constraint.
 */
class RuleVariantSelectorTest extends TestCase
{
    public function test_it_falls_back_to_the_rule_url_when_variant_weights_sum_to_zero(): void
    {
        $url = new Url;
        $url->value = 'https://fallback.example/lp';

        $variant = new RuleVariant;
        $variant->weight = 0;

        $rule = new Rule;
        $rule->url_id = 5;
        $rule->setRelation('url', $url);
        $rule->setRelation('variants', new Collection([$variant]));

        $result = (new RuleVariantSelector)->select($rule, new Link, Request::create('/'));

        $this->assertSame(5, $result['url_id']);
        $this->assertSame('https://fallback.example/lp', $result['url_value']);
        $this->assertNull($result['variant']);
    }
}
