<?php

namespace Tests\Feature\Clicks;

use App\Filament\Resources\Clicks\ClickResource;
use App\Models\Click;
use App\Models\Link;
use App\Models\Service;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the clicks sidebar badge (docs/08-decisions.md (review 2026-06) — M7): it counts only
 * today's clicks via an index-friendly range filter, not whereDate().
 */
class ClickNavigationBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_badge_counts_only_todays_clicks(): void
    {
        $service = Service::query()->create(['name' => 'Badge Service']);
        $link = Link::query()->create([
            'service_id' => $service->id,
            'forward_query' => false,
        ]);
        $url = Url::query()->create(['value' => 'https://target.example/badge']);

        $base = [
            'service_id' => $service->id,
            'link_id' => $link->id,
            'url_id' => $url->id,
        ];

        Click::query()->create($base);
        Click::query()->create($base);

        $old = Click::query()->create($base);
        Click::query()->whereKey($old->id)->update(['created_at' => now()->subDays(2)]);

        $this->assertSame('2', ClickResource::getNavigationBadge());
    }
}
