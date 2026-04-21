<?php

namespace Tests\Unit\Callbacks;

use App\Services\Links\Callbacks\CallbackUrlGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CallbackUrlGuardTest extends TestCase
{
    public static function safeUrls(): array
    {
        // IP literals only — DNS-based cases would require network access.
        return [
            'public ipv4 literal' => ['https://1.1.1.1/'],
            'public ipv4 with path and query' => ['http://8.8.8.8/webhook?x=1'],
        ];
    }

    #[DataProvider('safeUrls')]
    public function test_it_accepts_public_urls(string $url): void
    {
        $this->assertTrue((new CallbackUrlGuard)->isSafe($url));
    }

    #[DataProvider('unsafeUrls')]
    public function test_it_rejects_unsafe_urls(string $url): void
    {
        $this->assertFalse((new CallbackUrlGuard)->isSafe($url));
    }

    public static function unsafeUrls(): array
    {
        return [
            'malformed' => ['not-a-url'],
            'no scheme' => ['example.com/hook'],
            'empty host' => ['https://'],
            'ftp scheme' => ['ftp://example.com'],
            'file scheme' => ['file:///etc/passwd'],
            'gopher scheme' => ['gopher://example.com'],
            'localhost name' => ['http://localhost/hook'],
            'ip6-localhost name' => ['http://ip6-localhost/hook'],
            'loopback ipv4' => ['http://127.0.0.1/hook'],
            'loopback ipv4 any port' => ['http://127.0.0.1:8080/hook'],
            'loopback ipv6' => ['http://[::1]/hook'],
            'private 10.x' => ['http://10.0.0.5/hook'],
            'private 172.16.x' => ['http://172.16.0.1/hook'],
            'private 192.168.x' => ['http://192.168.1.1/hook'],
            'aws metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'link-local' => ['http://169.254.1.1/'],
            'unspecified' => ['http://0.0.0.0/hook'],
        ];
    }
}
