<?php

namespace Tests\Feature\Regressions;

use Tests\TestCase;

/**
 * Regression for docs/08-decisions.md (audit 2026-06) — H4.
 *
 * config/hashids.php used to default the salt to config('app.key')
 * (`env('HASHIDS_SALT', config('app.key'))`). That coupled short-code stability
 * to the encryption key — rotating APP_KEY changed every id<->code mapping — and
 * let anyone who learns APP_KEY decode codes back to sequential link ids. The
 * salt must come only from its own dedicated HASHIDS_SALT secret.
 */
class HashidsSaltConfigTest extends TestCase
{
    public function test_config_does_not_fall_back_to_app_key(): void
    {
        // Guard the exact regression: a `config('app.key')` fallback would
        // recouple short-code stability to the encryption key. (Matches the call,
        // not the explanatory comment.)
        $source = file_get_contents(base_path('config/hashids.php'));

        $this->assertStringNotContainsString("config('app.key')", $source);
        $this->assertStringContainsString("env('HASHIDS_SALT')", $source);
    }

    public function test_salt_is_sourced_only_from_its_env_var(): void
    {
        // The configured salt is exactly HASHIDS_SALT, with no fallback applied.
        $this->assertSame(env('HASHIDS_SALT'), config('hashids.salt'));
    }
}
