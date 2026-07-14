<?php

namespace Tests\Unit\Support;

use App\Support\TrustedProxies;
use PHPUnit\Framework\TestCase;

class TrustedProxiesTest extends TestCase
{
    public function test_a_comma_separated_list_is_parsed_and_trimmed(): void
    {
        $this->assertSame(
            ['10.1.2.0/28', '203.0.113.5'],
            TrustedProxies::fromEnv(' 10.1.2.0/28 , 203.0.113.5 '),
        );
    }

    public function test_asterisk_trusts_any_proxy(): void
    {
        $this->assertSame('*', TrustedProxies::fromEnv('*'));
    }

    public function test_blank_entries_are_dropped(): void
    {
        $this->assertSame(
            ['10.1.2.0/28'],
            TrustedProxies::fromEnv('10.1.2.0/28,,'),
        );
    }

    public function test_unset_falls_back_to_the_rfc1918_ranges(): void
    {
        $this->assertSame(TrustedProxies::DEFAULT_RANGES, TrustedProxies::fromEnv(null));
        $this->assertSame(TrustedProxies::DEFAULT_RANGES, TrustedProxies::fromEnv(''));
        $this->assertSame(TrustedProxies::DEFAULT_RANGES, TrustedProxies::fromEnv('   '));
    }
}
