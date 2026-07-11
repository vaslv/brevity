<?php

namespace Tests\Feature\Regressions;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guard for docs/08-decisions.md (review 2026-06) — m8.
 *
 * The Filament user form exposes an email_verified_at field; it must be
 * mass-assignable, otherwise saves silently drop it.
 */
class UserEmailVerifiedAtFillableTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verified_at_is_mass_assignable(): void
    {
        $verifiedAt = now()->startOfSecond();

        $user = User::query()->create([
            'name' => 'Verified Admin',
            'email' => 'verified@example.test',
            'password' => 'password',
            'email_verified_at' => $verifiedAt,
        ]);

        $this->assertNotNull($user->refresh()->email_verified_at);
        $this->assertTrue($verifiedAt->equalTo($user->email_verified_at));
    }
}
