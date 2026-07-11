<?php

namespace Tests\Feature\Regressions;

use App\Models\User;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Regression for docs/08-decisions.md (audit 2026-06) — M5.
 *
 * The viewHorizon gate authorized any authenticated user ($user !== null),
 * independent of panel access. It now delegates to canAccessPanel so Horizon
 * follows the same access rule as the admin panel (and any future is_admin flag
 * applies to both at once).
 */
class HorizonGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_panel_user_may_view_horizon(): void
    {
        $user = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $this->assertTrue(Gate::forUser($user)->allows('viewHorizon'));
    }

    public function test_a_user_without_panel_access_may_not_view_horizon(): void
    {
        $blocked = new class extends User
        {
            public function canAccessPanel(Panel $panel): bool
            {
                return false;
            }
        };

        $this->assertFalse(Gate::forUser($blocked)->allows('viewHorizon'));
    }
}
