<?php

namespace Tests\Feature\Regressions;

use Tests\TestCase;

/**
 * The application timezone was hardcoded to 'UTC' in config/app.php, so the
 * APP_TIMEZONE env var (set to Europe/Moscow in production) was silently
 * ignored. It now reads from env. This guards against reverting to a literal.
 */
class AppTimezoneFromEnvTest extends TestCase
{
    public function test_timezone_falls_back_to_utc_when_unset(): void
    {
        $config = $this->configWithEnv('APP_TIMEZONE', null);

        $this->assertSame('UTC', $config['timezone']);
    }

    public function test_timezone_is_driven_by_the_app_timezone_env(): void
    {
        $config = $this->configWithEnv('APP_TIMEZONE', 'Asia/Tokyo');

        $this->assertSame('Asia/Tokyo', $config['timezone']);
    }

    /**
     * Re-evaluate config/app.php with APP_TIMEZONE forced to $value (or removed
     * when null), then restore the original environment.
     *
     * @return array<string, mixed>
     */
    private function configWithEnv(string $key, ?string $value): array
    {
        $originalEnv = $_ENV[$key] ?? null;
        $originalServer = $_SERVER[$key] ?? null;
        $originalPutenv = getenv($key);

        try {
            if ($value === null) {
                unset($_ENV[$key], $_SERVER[$key]);
                putenv($key);
            } else {
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
                putenv("{$key}={$value}");
            }

            return require base_path('config/app.php');
        } finally {
            $this->restoreEnv($key, $originalEnv, $originalServer, $originalPutenv);
        }
    }

    private function restoreEnv(string $key, ?string $env, ?string $server, string|false $putenv): void
    {
        if ($env === null) {
            unset($_ENV[$key]);
        } else {
            $_ENV[$key] = $env;
        }

        if ($server === null) {
            unset($_SERVER[$key]);
        } else {
            $_SERVER[$key] = $server;
        }

        if ($putenv === false) {
            putenv($key);
        } else {
            putenv("{$key}={$putenv}");
        }
    }
}
