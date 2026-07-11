<?php

namespace Tests\Feature\Regressions;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression for docs/08-decisions.md (audit 2026-06) — M15.
 *
 * The Users table exposed a DeleteBulkAction with no guard, so an admin could
 * bulk-delete every user (all users are admins) and lock everyone out. Users are
 * no longer deletable from the panel.
 */
class UsersNotDeletableTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_cannot_be_bulk_deleted_from_the_panel(): void
    {
        $this->actingAs(User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]));
        Filament::setCurrentPanel('main');

        Livewire::test(ListUsers::class)
            ->assertTableBulkActionDoesNotExist('delete');
    }
}
