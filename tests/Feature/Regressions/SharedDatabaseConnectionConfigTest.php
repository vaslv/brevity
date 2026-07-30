<?php

namespace Tests\Feature\Regressions;

use Dotenv\Repository\RepositoryBuilder;
use Illuminate\Support\Env;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Guards the optional "shared" connection in config/database.php.
 *
 * The connection is off by default: with no SHARED_DB_* variables set it must
 * fall back to the primary DB_* settings so it mirrors the default connection
 * and stays harmless for forks that never use it. Only explicit SHARED_DB_*
 * overrides should repoint it at another database.
 */
class SharedDatabaseConnectionConfigTest extends TestCase
{
    /**
     * An empty password is a legitimate value, so that key keeps env()'s own
     * default and stays empty instead of inheriting DB_PASSWORD.
     */
    public function test_empty_shared_password_is_kept(): void
    {
        $config = $this->configWithEmptySharedVariables();

        $this->assertSame('', $config['connections']['shared']['password']);
    }

    /**
     * An empty SHARED_DB_* key must mirror the primary settings too.
     *
     * env()'s second argument only applies when the variable is absent, so
     * "SHARED_DB_CONNECTION=" used to resolve to an empty string and the
     * connection died with "Unsupported driver []" — even though .env.example
     * documents leaving the keys empty as the way to mirror DB_*.
     */
    public function test_empty_shared_variables_fall_back_to_primary_settings(): void
    {
        $config = $this->configWithEmptySharedVariables();

        $this->assertSame(env('DB_CONNECTION', 'pgsql'), $config['connections']['shared']['driver']);
        $this->assertSame(env('DB_HOST', '127.0.0.1'), $config['connections']['shared']['host']);
        $this->assertSame(env('DB_PORT', '5432'), $config['connections']['shared']['port']);
        $this->assertSame(env('DB_DATABASE', 'laravel'), $config['connections']['shared']['database']);
        $this->assertSame(env('DB_USERNAME', 'root'), $config['connections']['shared']['username']);
        $this->assertSame(env('DB_CHARSET', 'utf8'), $config['connections']['shared']['charset']);
        $this->assertSame(env('DB_SSLMODE', 'prefer'), $config['connections']['shared']['sslmode']);
    }

    public function test_shared_connection_is_defined(): void
    {
        $this->assertIsArray(config('database.connections.shared'));
    }

    public function test_shared_connection_mirrors_primary_settings_by_default(): void
    {
        // No SHARED_DB_* variables are set in the test environment, so every key
        // must resolve to its primary DB_* fallback.
        $this->assertSame(env('DB_CONNECTION', 'pgsql'), config('database.connections.shared.driver'));
        $this->assertSame(env('DB_HOST', '127.0.0.1'), config('database.connections.shared.host'));
        $this->assertSame(env('DB_PORT', '5432'), config('database.connections.shared.port'));
        $this->assertSame(env('DB_DATABASE', 'laravel'), config('database.connections.shared.database'));
        $this->assertSame(env('DB_USERNAME', 'root'), config('database.connections.shared.username'));
        $this->assertSame(env('DB_PASSWORD', ''), config('database.connections.shared.password'));
    }

    /**
     * Re-read config/database.php with every SHARED_DB_* key set to an empty
     * string, against a throwaway repository so the process env stays clean.
     *
     * @return array<string, mixed>
     */
    private function configWithEmptySharedVariables(): array
    {
        $keys = [
            'SHARED_DB_CONNECTION',
            'SHARED_DB_URL',
            'SHARED_DB_HOST',
            'SHARED_DB_PORT',
            'SHARED_DB_DATABASE',
            'SHARED_DB_USERNAME',
            'SHARED_DB_PASSWORD',
            'SHARED_DB_CHARSET',
            'SHARED_DB_SSLMODE',
        ];

        $property = new ReflectionProperty(Env::class, 'repository');
        $original = $property->getValue();

        $repository = RepositoryBuilder::createWithDefaultAdapters()->make();

        foreach ($keys as $key) {
            $repository->set($key, '');
        }

        $property->setValue(null, $repository);

        try {
            return require base_path('config/database.php');
        } finally {
            $property->setValue(null, $original);

            foreach ($keys as $key) {
                $repository->clear($key);
            }
        }
    }
}
