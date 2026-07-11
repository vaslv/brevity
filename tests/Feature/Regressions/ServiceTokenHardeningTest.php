<?php

namespace Tests\Feature\Regressions;

use App\Filament\Resources\Services\Pages\ViewService;
use App\Models\Service;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression for docs/08-decisions.md (audit 2026-06) — M6.
 *
 * Service tokens never expired, had no secret-scanning prefix, and expired rows
 * were never pruned. Tokens now carry a prefix, expired tokens are pruned on a
 * schedule, and the panel can mint a token with an optional expiry.
 */
class ServiceTokenHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_token_can_be_created_with_an_expiry(): void
    {
        // The createToken action passes the chosen duration as expiresAt; verify
        // the underlying capability it relies on (the optional expiry select is a
        // thin wrapper over this).
        $service = Service::query()->create(['name' => 'Svc '.fake()->unique()->word()]);

        $token = $service->createToken('t', ['links:create'], now()->addDays(30))->accessToken;

        $this->assertNotNull($token->expires_at);
        $this->assertTrue($token->expires_at->isFuture());
    }

    public function test_expired_tokens_are_pruned_on_a_schedule(): void
    {
        $commands = collect(app(Schedule::class)->events())
            ->map(fn ($event): string => (string) ($event->command ?? ''));

        $this->assertTrue(
            $commands->contains(fn (string $command): bool => str_contains($command, 'sanctum:prune-expired')),
        );
    }

    public function test_new_tokens_carry_a_detectable_prefix(): void
    {
        $this->assertNotSame('', config('sanctum.token_prefix'));

        $service = Service::query()->create(['name' => 'Svc '.fake()->unique()->word()]);
        $plain = $service->createToken('t', ['links:create'])->plainTextToken;

        // Sanctum formats the plaintext as "{id}|{prefix}{random}".
        [, $secret] = explode('|', $plain, 2);
        $this->assertStringStartsWith(config('sanctum.token_prefix'), $secret);
    }

    public function test_the_create_token_action_exposes_an_expiry_option(): void
    {
        $this->actingAs(User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]));
        Filament::setCurrentPanel('main');

        $service = Service::query()->create(['name' => 'Svc '.fake()->unique()->word()]);

        Livewire::test(ViewService::class, ['record' => $service->getRouteKey()])
            ->mountAction('createToken')
            ->assertSchemaComponentExists('expires_in_days');
    }
}
