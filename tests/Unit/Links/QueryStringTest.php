<?php

namespace Tests\Unit\Links;

use App\Services\Links\QueryString;
use PHPUnit\Framework\TestCase;

class QueryStringTest extends TestCase
{
    public function test_build_roundtrips_a_dotted_key_without_mangling(): void
    {
        $this->assertSame('sub.id=abc', QueryString::build(['sub.id' => 'abc']));
    }

    public function test_parse_keeps_dotted_and_spaced_keys_literal(): void
    {
        $this->assertSame(
            ['sub.id' => 'abc', 'a b' => 'c'],
            QueryString::parse('sub.id=abc&a%20b=c'),
        );
    }

    public function test_parse_keeps_the_last_value_for_a_repeated_key(): void
    {
        $this->assertSame(['a' => '2'], QueryString::parse('a=1&a=2'));
    }

    public function test_parse_returns_empty_for_null_or_blank(): void
    {
        $this->assertSame([], QueryString::parse(null));
        $this->assertSame([], QueryString::parse(''));
    }

    public function test_parse_treats_a_bare_key_as_an_empty_value(): void
    {
        $this->assertSame(['flag' => ''], QueryString::parse('flag'));
    }

    public function test_parse_urldecodes_keys_and_values(): void
    {
        $this->assertSame(['a b' => 'x y'], QueryString::parse('a%20b=x%20y'));
    }
}
