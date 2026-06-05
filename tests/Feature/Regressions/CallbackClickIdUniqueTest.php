<?php

namespace Tests\Feature\Regressions;

use App\Models\Click;
use App\Models\Link;
use App\Models\Service;
use App\Models\Url;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression for docs/AUDIT_2026-06.md — H2.
 *
 * CallbackDispatcher relies on Callback::firstOrCreate(['click_id' => ...]) for
 * "one callback per click", but callbacks.click_id had no unique index — so a
 * concurrent RecordClickJob race could insert two callbacks for the same click
 * and enqueue duplicate webhooks. The unique index makes the second insert fail,
 * which firstOrCreate handles by re-selecting the winning row.
 */
class CallbackClickIdUniqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_second_callback_for_the_same_click_is_rejected(): void
    {
        $click = $this->createClick();

        DB::table('callbacks')->insert($this->callbackRow($click));

        $this->expectException(QueryException::class);

        DB::table('callbacks')->insert($this->callbackRow($click));
    }

    /**
     * @return array<string, mixed>
     */
    private function callbackRow(Click $click): array
    {
        return [
            'service_id' => $click->service_id,
            'click_id' => $click->id,
            'data' => json_encode([]),
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => now(),
        ];
    }

    private function createClick(): Click
    {
        $service = Service::query()->create(['name' => 'Svc '.fake()->unique()->word()]);
        $link = Link::query()->create(['service_id' => $service->id, 'forward_query' => false]);
        $url = Url::query()->create(['value' => 'https://example.com/'.fake()->unique()->slug()]);

        return Click::query()->create([
            'uuid' => (string) Str::uuid(),
            'service_id' => $service->id,
            'link_id' => $link->id,
            'url_id' => $url->id,
        ]);
    }
}
