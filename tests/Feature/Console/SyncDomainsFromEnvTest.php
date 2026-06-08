<?php

namespace Tests\Feature\Console;

use App\Models\Domain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncDomainsFromEnvTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_handles_an_empty_host_list(): void
    {
        config([
            'app.technical_host' => 'tech.test',
            'app.hosts' => [],
        ]);

        $this->artisan('domains:sync')->assertSuccessful();

        $this->assertSame(0, Domain::query()->count());
    }

    public function test_it_is_idempotent_and_preserves_existing_domains(): void
    {
        $default = Domain::query()->create(['value' => 's1.example', 'is_default' => true]);

        config([
            'app.technical_host' => 'tech.test',
            'app.hosts' => ['tech.test', 's1.example', 's2.example'],
        ]);

        $this->artisan('domains:sync')->assertSuccessful();
        $this->artisan('domains:sync')->assertSuccessful();

        $this->assertSame(2, Domain::query()->count());
        $this->assertSame(1, Domain::query()->where('value', 's1.example')->count());
        // The pre-existing default flag is untouched by the sync.
        $this->assertTrue($default->fresh()->is_default);
    }

    public function test_it_matches_existing_domains_case_insensitively(): void
    {
        Domain::query()->create(['value' => 'S1.EXAMPLE']);

        config([
            'app.technical_host' => 'tech.test',
            'app.hosts' => ['tech.test', 's1.example'],
        ]);

        $this->artisan('domains:sync')->assertSuccessful();

        // No second row created for the differently-cased host.
        $this->assertSame(1, Domain::query()->count());
        $this->assertTrue(Domain::query()->where('value', 'S1.EXAMPLE')->exists());
    }

    public function test_it_seeds_short_link_hosts_and_skips_the_technical_host(): void
    {
        config([
            'app.technical_host' => 'tech.test',
            'app.hosts' => ['tech.test', 's1.example', 's2.example'],
        ]);

        $this->artisan('domains:sync')->assertSuccessful();

        $this->assertTrue(Domain::query()->where('value', 's1.example')->exists());
        $this->assertTrue(Domain::query()->where('value', 's2.example')->exists());
        $this->assertFalse(Domain::query()->where('value', 'tech.test')->exists());
        $this->assertSame(2, Domain::query()->count());
    }
}
