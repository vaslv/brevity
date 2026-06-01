<?php

namespace Tests\Feature\Regressions;

use App\Filament\Resources\Conditions\Pages\CreateCondition;
use App\Models\Condition;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Guard for docs/CODE_REVIEW.md — m6.
 *
 * Conditions are deduplicated by (type, data). Creating a duplicate in the
 * admin must reuse the existing row instead of raising a unique-constraint 500.
 */
class ConditionCreateDedupTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_duplicate_condition_reuses_the_existing_row(): void
    {
        $this->actingAs(User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]));
        Filament::setCurrentPanel('main');

        $form = ['type' => 'time_before', 'data' => ['before' => '2026-03-05 10:00:00']];

        Livewire::test(CreateCondition::class)->fillForm($form)->call('create')->assertHasNoFormErrors();
        Livewire::test(CreateCondition::class)->fillForm($form)->call('create')->assertHasNoFormErrors();

        $this->assertSame(1, Condition::query()->count());
    }
}
