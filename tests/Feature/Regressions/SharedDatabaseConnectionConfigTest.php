<?php

namespace Tests\Feature\Regressions;

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
}
