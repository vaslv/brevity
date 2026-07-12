<?php

namespace Tests\Unit\Conditions;

use App\Models\Condition;
use App\Models\Link;
use App\Services\Links\Conditions\ConditionContext;
use App\Services\Links\Conditions\IpAddressConditionHandler;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Stage 3 of docs/07-plans.md — the ip_address condition: matches the visitor
 * IP against an exact address, a CIDR range, or an IPv4 wildcard. Validation
 * accepts those three forms and rejects garbage.
 */
class IpAddressConditionHandlerTest extends TestCase
{
    public function test_a_stored_garbage_pattern_fails_closed_at_match_time(): void
    {
        // A bad mask (/999) hand-inserted or stored before validation must NOT
        // coerce into IpUtils' /0 (which would match every visitor).
        $this->assertFalse($this->matchResult('10.0.0.0/999', '8.8.8.8'));
        $this->assertFalse($this->matchResult('nonsense', '8.8.8.8'));
    }

    public function test_cidr_match(): void
    {
        $this->assertTrue($this->matchResult('10.0.0.0/8', '10.11.12.13'));
        $this->assertFalse($this->matchResult('10.0.0.0/8', '11.0.0.1'));
    }

    public function test_exact_match(): void
    {
        $this->assertTrue($this->matchResult('203.0.113.7', '203.0.113.7'));
        $this->assertFalse($this->matchResult('203.0.113.7', '203.0.113.8'));
    }

    public function test_ipv6_exact_match(): void
    {
        $this->assertTrue($this->matchResult('2001:db8::1', '2001:db8::1'));
    }

    public function test_it_fails_closed_on_malformed_data(): void
    {
        $this->assertFalse($this->matchResult(null, '203.0.113.7'));
    }

    public function test_the_type_slug_is_derived_from_the_class_name(): void
    {
        $this->assertSame('ip_address', IpAddressConditionHandler::type());
    }

    public function test_validation_accepts_the_three_forms_and_rejects_garbage(): void
    {
        foreach (['203.0.113.7', '10.0.0.0/24', '11.22.*.*', '2001:db8::/32'] as $valid) {
            $this->assertTrue($this->validates($valid), "expected {$valid} to validate");
        }

        foreach (['not-an-ip', '999.1.2.3', '10.0.0.0/999', 'abc.*.*.*'] as $invalid) {
            $this->assertFalse($this->validates($invalid), "expected {$invalid} to be rejected");
        }
    }

    public function test_wildcard_match_is_anchored(): void
    {
        $this->assertTrue($this->matchResult('11.22.*.*', '11.22.33.44'));
        $this->assertFalse($this->matchResult('11.22.*.*', '11.23.33.44'));
        // Anchored: the wildcard must not match a longer leading octet.
        $this->assertFalse($this->matchResult('11.22.*.*', '211.22.33.44'));
    }

    private function matchResult(?string $pattern, string $visitorIp): bool
    {
        $condition = new Condition(['type' => 'ip_address', 'data' => ['ip' => $pattern]]);
        $context = new ConditionContext(
            new Link,
            Request::create('/', server: ['REMOTE_ADDR' => $visitorIp]),
            CarbonImmutable::now(),
        );

        return (new IpAddressConditionHandler)->matches($condition, $context);
    }

    private function validates(string $ip): bool
    {
        return ! Validator::make(['ip' => $ip], IpAddressConditionHandler::rules())->fails();
    }
}
