<?php

namespace Tests\Feature\Regressions;

use App\Filament\Resources\Urls\Pages\CreateUrl;
use App\Models\Link;
use App\Models\Rule;
use App\Models\Service;
use App\Models\Url;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression for docs/08-decisions.md (audit 2026-06) — M10.
 *
 * redirect()->away() / the interstitial href emitted whatever scheme was stored
 * in urls.value. The API enforces http/https on write, but seeders/raw SQL/admin
 * edits could bypass that. The resolver now re-validates the scheme at read time
 * (and the admin UrlForm validates it on write).
 */
class TargetUrlSchemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_non_http_target_scheme_is_not_resolved(): void
    {
        // Bypasses write-time validation (raw insert) to simulate stale/legacy data.
        $service = Service::query()->create(['name' => 'Svc '.fake()->unique()->word()]);
        $link = Link::query()->create(['service_id' => $service->id, 'forward_query' => false]);
        $url = Url::query()->create(['value' => 'javascript:alert(1)']);
        $code = 'abcdEFGH';
        $link->update(['code' => $code]);
        Rule::query()->create(['link_id' => $link->id, 'url_id' => $url->id, 'priority' => 1]);

        $this->get(static::SHORT_LINK_HOST.'/'.$code)->assertNotFound();
    }

    public function test_admin_url_form_rejects_a_non_http_scheme(): void
    {
        $this->actingAs(User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]));
        Filament::setCurrentPanel('main');

        Livewire::test(CreateUrl::class)
            ->fillForm(['value' => 'javascript:alert(1)'])
            ->call('create')
            ->assertHasFormErrors(['value']);
    }
}
