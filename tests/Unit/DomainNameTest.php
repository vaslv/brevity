<?php

namespace Tests\Unit;

use App\Services\Links\Domains\DomainName;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DomainNameTest extends TestCase
{
    public static function invalidNames(): array
    {
        return array_map(fn (string $value): array => [$value], [
            '', ' ', '.', 'пример..рф', 'пример.рф..', 'https://пример.рф',
            'пример.рф/path', 'пример.рф:443', 'user@пример.рф', 'пример.рф?x=1',
            'пример.рф#fragment', '*.пример.рф', '_service.example', '-bad.test',
            'bad-.test', 'xn--.test', 'xn--a.test', "bad\0.test", "bad\n.test", "\xff.test",
            'a b.test', 'a\\b.test', 'a%20b.test', "a\u{200D}b.test", 'aא.test',
            str_repeat('a', 64).'.test', str_repeat('я', 60).'.рф',
            implode('.', array_fill(0, 4, str_repeat('a', 63))),
        ]);
    }

    #[DataProvider('validNames')]
    public function test_normalizes_domains_and_round_trips_display(string $input, string $ascii, string $unicode): void
    {
        $this->assertSame($ascii, DomainName::toAscii($input));
        $this->assertSame($ascii, DomainName::toAscii($ascii));
        $this->assertSame($unicode, DomainName::toUnicode($ascii));
        $this->assertSame($ascii, DomainName::toAscii($unicode));
    }

    #[DataProvider('invalidNames')]
    public function test_rejects_invalid_domains(string $input): void
    {
        $this->assertNull(DomainName::tryToAscii($input));
        $this->expectException(InvalidArgumentException::class);
        DomainName::toAscii($input);
    }

    public static function validNames(): array
    {
        return [
            ['пример.рф', 'xn--e1afmkfd.xn--p1ai', 'пример.рф'],
            [' ПРИМЕР.РФ. ', 'xn--e1afmkfd.xn--p1ai', 'пример.рф'],
            ['XN--E1AFMKFD.XN--P1AI', 'xn--e1afmkfd.xn--p1ai', 'пример.рф'],
            ['Example.COM.', 'example.com', 'example.com'],
            ['www.пример.рф', 'www.xn--e1afmkfd.xn--p1ai', 'www.пример.рф'],
            ['пример。рф', 'xn--e1afmkfd.xn--p1ai', 'пример.рф'],
            ['faß.de', 'xn--fa-hia.de', 'faß.de'],
            ["bu\u{0308}cher.de", 'xn--bcher-kva.de', 'bücher.de'],
            ['localhost', 'localhost', 'localhost'],
        ];
    }
}
