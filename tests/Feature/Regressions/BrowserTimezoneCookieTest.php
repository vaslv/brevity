<?php

namespace Tests\Feature\Regressions;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Regression for docs/07-plans.md — r38.
 *
 * The browser-timezone render hook sets a plaintext `tz` cookie via
 * document.cookie. The panel runs the `web` group, whose EncryptCookies nulls
 * any cookie it cannot decrypt, so without an explicit exception `tz` never
 * reaches AppServiceProvider's FilamentTimezone closure and the panel silently
 * renders (and parses condition times) in UTC.
 */
class BrowserTimezoneCookieTest extends TestCase
{
    public function test_plaintext_tz_cookie_survives_cookie_encryption(): void
    {
        Route::middleware('web')->get(
            '/__tz_probe',
            fn (): string => request()->cookie('tz') ?? 'NULL',
        );

        $this->withUnencryptedCookie('tz', 'Europe/Moscow')
            ->get('/__tz_probe')
            ->assertOk()
            ->assertSee('Europe/Moscow');
    }
}
