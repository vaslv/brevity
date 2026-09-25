<?php

namespace Tests\Unit;

use App\Services\Links\Domains\DomainName;
use App\Support\HttpHost;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HttpHostTest extends TestCase
{
    /**
     * @return array<array{string}>
     */
    public static function invalidHosts(): array
    {
        return array_map(fn (string $host): array => [$host], [
            '', '[not-an-ip]', '[127.0.0.1]', '[::1', '::1]', '[::1]:80',
            '[::1]/path', 'http://[::1]', 'user@[::1]', 'example.com:80',
            '[::gg]', '[[::1]]', 'xn--a.test',
        ]);
    }

    public function test_http_host_support_does_not_relax_domain_validation(): void
    {
        $this->assertNull(DomainName::tryToAscii('[::1]'));
    }

    #[DataProvider('validHosts')]
    public function test_normalizes_http_hosts(string $input, string $expected): void
    {
        $this->assertSame($expected, HttpHost::normalize($input));
        $this->assertSame($expected, HttpHost::normalize($expected));
    }

    #[DataProvider('invalidHosts')]
    public function test_rejects_invalid_http_hosts(string $input): void
    {
        $this->assertNull(HttpHost::tryNormalize($input));
        $this->expectException(InvalidArgumentException::class);
        HttpHost::normalize($input);
    }

    /**
     * @return array<array{string, string}>
     */
    public static function validHosts(): array
    {
        return [
            ['[::1]', '[::1]'],
            [' [2001:0DB8:0:0:0:0:0:1] ', '[2001:db8::1]'],
            ['[::ffff:192.0.2.1]', '[::ffff:192.0.2.1]'],
            ['127.0.0.1', '127.0.0.1'],
            ['LOCALHOST', 'localhost'],
            ['EXAMPLE.COM.', 'example.com'],
            ['ПРИМЕР.РФ', 'xn--e1afmkfd.xn--p1ai'],
            ['XN--E1AFMKFD.XN--P1AI', 'xn--e1afmkfd.xn--p1ai'],
        ];
    }
}
