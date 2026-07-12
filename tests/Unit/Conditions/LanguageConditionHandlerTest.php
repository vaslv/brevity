<?php

namespace Tests\Unit\Conditions;

use App\Models\Condition;
use App\Models\Link;
use App\Services\Links\Conditions\ConditionContext;
use App\Services\Links\Conditions\LanguageConditionHandler;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Stage 3 of docs/07-plans.md — the language condition. Matches when
 * Accept-Language strongly prefers (quality ≥ 0.9) the configured language,
 * optionally scoped to a country. An empty header or `*` never matches.
 */
class LanguageConditionHandlerTest extends TestCase
{
    public function test_a_longer_subtag_does_not_falsely_match(): void
    {
        // 'enm' (Middle English) must not satisfy a rule keyed on 'en'.
        $this->assertFalse($this->matchResult('en', null, 'enm'));
    }

    public function test_an_absent_quality_defaults_to_full_and_matches(): void
    {
        $this->assertTrue($this->matchResult('en', null, 'en-US'));
    }

    public function test_country_scope_requires_an_exact_match(): void
    {
        $this->assertTrue($this->matchResult('en', 'US', 'en-US'));
        $this->assertFalse($this->matchResult('en', 'US', 'en-GB'));
    }

    public function test_it_fails_closed_on_malformed_data(): void
    {
        $this->assertFalse($this->matchResult(null, null, 'en-US'));
        $this->assertFalse($this->matchResult('', null, 'en-US'));
    }

    public function test_language_only_matches_on_the_subtag(): void
    {
        $this->assertTrue($this->matchResult('en', null, 'en-US,en;q=0.9'));
        $this->assertTrue($this->matchResult('en', null, 'en-GB'));
        $this->assertFalse($this->matchResult('fr', null, 'en-US,en;q=0.9'));
    }

    public function test_low_quality_preference_does_not_match(): void
    {
        // The browser only weakly accepts German — below the 0.9 threshold.
        $this->assertFalse($this->matchResult('de', null, 'en-US,en;q=0.9,de;q=0.5'));
    }

    public function test_matching_is_case_insensitive(): void
    {
        $this->assertTrue($this->matchResult('en', 'us', 'EN-US'));
    }

    public function test_the_quality_threshold_is_inclusive(): void
    {
        // Exactly 0.9 matches; just below does not.
        $this->assertTrue($this->matchResult('de', null, 'de;q=0.9'));
        $this->assertFalse($this->matchResult('de', null, 'de;q=0.89'));
    }

    public function test_the_type_slug_is_derived_from_the_class_name(): void
    {
        $this->assertSame('language', LanguageConditionHandler::type());
    }

    public function test_validation_accepts_valid_and_rejects_invalid(): void
    {
        $this->assertFalse($this->validate(['language' => 'en', 'country' => 'US'])->fails());
        $this->assertFalse($this->validate(['language' => 'ru'])->fails());
        $this->assertTrue($this->validate(['language' => 'english'])->fails());
        $this->assertTrue($this->validate(['language' => 'en', 'country' => 'USA'])->fails());
    }

    public function test_wildcard_and_empty_header_never_match(): void
    {
        $this->assertFalse($this->matchResult('en', null, '*'));
        $this->assertFalse($this->matchResult('en', null, ''));
    }

    private function matchResult(?string $language, ?string $country, string $acceptLanguage): bool
    {
        $condition = new Condition(['type' => 'language', 'data' => ['language' => $language, 'country' => $country]]);
        // Always send the header explicitly (even empty): Request::create()
        // otherwise injects a default Accept-Language, which would mask the
        // empty-header case.
        $context = new ConditionContext(
            new Link,
            Request::create('/', server: ['HTTP_ACCEPT_LANGUAGE' => $acceptLanguage]),
            CarbonImmutable::now(),
        );

        return (new LanguageConditionHandler)->matches($condition, $context);
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, LanguageConditionHandler::rules());
    }
}
