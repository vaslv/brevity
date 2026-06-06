<?php

namespace Tests\Feature\Regressions;

use App\Filament\Resources\Urls\Pages\CreateUrl;
use App\Filament\Resources\Urls\Pages\ListUrls;
use App\Models\Click;
use App\Models\Link;
use App\Models\Service;
use App\Models\Url;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression for docs/AUDIT_2026-06.md — M7.
 *
 * Creating a duplicate Url/Domain value and bulk-deleting a dictionary row that
 * is still referenced (clicks/rules, restrictOnDelete) both surfaced a raw
 * Postgres QueryException (500). The forms now validate uniqueness and the
 * dictionary tables use a RestrictedDeleteBulkAction that reports a friendly
 * notification instead.
 */
class FilamentFriendlyErrorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_deleting_a_referenced_url_is_blocked_without_a_500(): void
    {
        $referenced = $this->referencedUrl();

        Livewire::test(ListUrls::class)
            ->callTableBulkAction('delete', [$referenced]);

        // The FK restriction is reported as a notification; the row survives.
        $this->assertDatabaseHas('urls', ['id' => $referenced->id]);
    }

    public function test_bulk_deleting_an_unreferenced_url_succeeds(): void
    {
        $url = Url::query()->create(['value' => 'https://free.example/'.fake()->unique()->slug()]);

        Livewire::test(ListUrls::class)
            ->callTableBulkAction('delete', [$url]);

        $this->assertDatabaseMissing('urls', ['id' => $url->id]);
    }

    public function test_creating_a_duplicate_url_shows_a_validation_error(): void
    {
        Url::query()->create(['value' => 'https://dup.example/landing']);

        Livewire::test(CreateUrl::class)
            ->fillForm(['value' => 'https://dup.example/landing'])
            ->call('create')
            ->assertHasFormErrors(['value' => 'unique']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::query()->create([
            'name' => 'Admin',
            'email' => 'admin'.fake()->unique()->randomNumber().'@example.test',
            'password' => 'password',
        ]));
        Filament::setCurrentPanel('main');
    }

    private function referencedUrl(): Url
    {
        $service = Service::query()->create(['name' => 'Svc '.fake()->unique()->word()]);
        $link = Link::query()->create(['service_id' => $service->id, 'forward_query' => false]);
        $url = Url::query()->create(['value' => 'https://used.example/'.fake()->unique()->slug()]);

        Click::query()->create([
            'uuid' => (string) Str::uuid(),
            'service_id' => $service->id,
            'link_id' => $link->id,
            'url_id' => $url->id,
        ]);

        return $url;
    }
}
