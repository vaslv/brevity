<?php

namespace Tests\Feature\Links;

use App\Models\Callback;
use App\Models\Click;
use App\Models\IpAddress;
use App\Models\Link;
use App\Models\LinkClickCounter;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Stage 2 of docs/07-plans.md — the prune commands.
 *
 * links:prune-dead soft-deletes expired/exhausted links (OR of the explicit
 * flags; no flags = no-op safety; --dry-run counts only). ips:prune enforces
 * the IP retention decision (2026-07-11). clicks:prune wipes one link's
 * clicks with callbacks cascading and counters reset in the same transaction.
 */
class PruneCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_clicks_prune_requires_an_existing_link(): void
    {
        $this->artisan('clicks:prune --link=missing')->assertFailed();
        $this->artisan('clicks:prune')->assertFailed();
    }

    public function test_clicks_prune_wipes_one_links_clicks_callbacks_and_counters(): void
    {
        $service = Service::factory()->create(['callback_url' => 'https://93.184.216.34/hook']);
        $link = Link::factory()->create(['service_id' => $service->id]);
        $link->update(['code' => fake()->unique()->bothify('????####')]);
        $other = Link::factory()->create();

        $click = Click::factory()->create(['link_id' => $link->id, 'service_id' => $service->id]);
        Callback::query()->create([
            'service_id' => $service->id, 'click_id' => $click->id,
            'data' => [], 'status' => 'pending', 'attempts' => 0,
        ]);
        LinkClickCounter::query()->create(['link_id' => $link->id, 'is_bot' => false, 'slot' => 1, 'count' => 1]);

        $otherClick = Click::factory()->create(['link_id' => $other->id]);
        LinkClickCounter::query()->create(['link_id' => $other->id, 'is_bot' => false, 'slot' => 1, 'count' => 1]);

        $this->artisan('clicks:prune --link='.$link->code)
            ->expectsOutputToContain('Deleted 1 click(s)')
            ->assertSuccessful();

        $this->assertSame(0, Click::query()->where('link_id', $link->id)->count());
        $this->assertSame(0, Callback::query()->count());
        $this->assertSame(0, LinkClickCounter::query()->where('link_id', $link->id)->count());
        $this->assertNotNull($otherClick->refresh());
        $this->assertSame(1, LinkClickCounter::query()->where('link_id', $other->id)->count());
    }

    public function test_clicks_prune_works_for_a_soft_deleted_link(): void
    {
        $link = Link::factory()->create();
        $link->update(['code' => fake()->unique()->bothify('????####')]);
        Click::factory()->create(['link_id' => $link->id]);
        $link->delete();

        $this->artisan('clicks:prune --link='.$link->code)
            ->expectsOutputToContain('Deleted 1 click(s)')
            ->assertSuccessful();
    }

    public function test_ips_prune_aborts_when_another_run_holds_the_lock(): void
    {
        $lock = Cache::lock('ips:prune', 60);
        $this->assertTrue($lock->get());

        try {
            $this->artisan('ips:prune')->assertFailed();
        } finally {
            $lock->release();
        }
    }

    public function test_ips_prune_detaches_old_clicks_and_deletes_orphaned_rows(): void
    {
        config()->set('tracking.ip_retention_days', 90);

        $oldIp = IpAddress::query()->create(['value' => '203.0.113.10']);
        $freshIp = IpAddress::query()->create(['value' => '203.0.113.20']);

        $oldClick = Click::factory()->create(['ip_address_id' => $oldIp->id]);
        Click::query()->whereKey($oldClick->id)->update(['created_at' => now()->subDays(91)]);

        $freshClick = Click::factory()->create(['ip_address_id' => $freshIp->id]);

        $this->artisan('ips:prune')->assertSuccessful();

        $this->assertNull($oldClick->refresh()->ip_address_id);
        $this->assertSame($freshIp->id, $freshClick->refresh()->ip_address_id);
        $this->assertDatabaseMissing('ip_addresses', ['id' => $oldIp->id]);
        $this->assertDatabaseHas('ip_addresses', ['id' => $freshIp->id]);
    }

    public function test_prune_dead_aborts_when_another_run_holds_the_lock(): void
    {
        $expired = Link::factory()->create(['valid_until' => now()->subDay()]);
        $lock = Cache::lock('links:prune-dead', 60);
        $this->assertTrue($lock->get());

        try {
            $this->artisan('links:prune-dead --expired')->assertFailed();
        } finally {
            $lock->release();
        }

        $this->assertNull($expired->refresh()->deleted_at);
    }

    public function test_prune_dead_combines_conditions_with_or(): void
    {
        $expired = Link::factory()->expired()->create();
        $exhausted = Link::factory()->withMaxClicks(1)->create();
        LinkClickCounter::query()->create(['link_id' => $exhausted->id, 'is_bot' => false, 'slot' => 1, 'count' => 1]);
        $alive = Link::factory()->create();

        $this->artisan('links:prune-dead --expired --exhausted')
            ->expectsOutputToContain('2 dead link(s)')
            ->assertSuccessful();

        $this->assertNotNull($expired->refresh()->deleted_at);
        $this->assertNotNull($exhausted->refresh()->deleted_at);
        $this->assertNull($alive->refresh()->deleted_at);
    }

    public function test_prune_dead_dry_run_counts_without_deleting(): void
    {
        Link::factory()->expired()->create();

        $this->artisan('links:prune-dead --expired --dry-run')
            ->expectsOutputToContain('[dry-run] 1 link(s)')
            ->assertSuccessful();

        $this->assertSame(0, Link::onlyTrashed()->count());
    }

    public function test_prune_dead_exhausted_flag_uses_counter_sums(): void
    {
        $exhausted = Link::factory()->withMaxClicks(5)->create();
        LinkClickCounter::query()->create(['link_id' => $exhausted->id, 'is_bot' => false, 'slot' => 1, 'count' => 3]);
        LinkClickCounter::query()->create(['link_id' => $exhausted->id, 'is_bot' => true, 'slot' => 1, 'count' => 2]);

        $below = Link::factory()->withMaxClicks(5)->create();
        LinkClickCounter::query()->create(['link_id' => $below->id, 'is_bot' => false, 'slot' => 1, 'count' => 4]);

        $this->artisan('links:prune-dead --exhausted')->assertSuccessful();

        $this->assertNotNull($exhausted->refresh()->deleted_at);
        $this->assertNull($below->refresh()->deleted_at);
    }

    public function test_prune_dead_expired_flag_soft_deletes_only_expired(): void
    {
        $expired = Link::factory()->expired()->create();
        $alive = Link::factory()->create(['valid_until' => now()->addDay()]);
        $unlimited = Link::factory()->create();

        $this->artisan('links:prune-dead --expired')
            ->expectsOutputToContain('1 dead link(s)')
            ->assertSuccessful();

        $this->assertNotNull($expired->refresh()->deleted_at);
        $this->assertNull($alive->refresh()->deleted_at);
        $this->assertNull($unlimited->refresh()->deleted_at);
    }

    public function test_prune_dead_rerun_does_not_touch_already_trashed(): void
    {
        Link::factory()->expired()->create();

        $this->artisan('links:prune-dead --expired')->assertSuccessful();
        // SoftDeletes scope: the second run must see nothing to prune.
        $this->artisan('links:prune-dead --expired')
            ->expectsOutputToContain('0 dead link(s)')
            ->assertSuccessful();
    }

    public function test_prune_dead_without_flags_deletes_nothing(): void
    {
        Link::factory()->expired()->create();

        $this->artisan('links:prune-dead')->assertSuccessful();

        $this->assertSame(0, Link::onlyTrashed()->count());
    }
}
