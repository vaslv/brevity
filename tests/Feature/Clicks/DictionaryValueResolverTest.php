<?php

namespace Tests\Feature\Clicks;

use App\Models\IpAddress;
use App\Models\Referrer;
use App\Services\Links\Clicks\DictionaryValueResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers docs/08-decisions.md (audit 2026-06) — Low: dictionary resolver hot path.
 *
 * The resolver used insertOrIgnore + a separate SELECT, i.e. two queries on
 * every resolve even though referrers/user-agents/IPs recur heavily and the row
 * almost always already exists. It now resolves a known value in a single SELECT
 * and only inserts (race-safely, via ON CONFLICT DO NOTHING RETURNING) on a miss.
 */
class DictionaryValueResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_value_resolves_in_a_single_query_without_duplicating(): void
    {
        $resolver = new DictionaryValueResolver;

        $first = $resolver->resolveId(Referrer::class, 'https://example.com/landing');

        DB::enableQueryLog();
        DB::flushQueryLog();

        $second = $resolver->resolveId(Referrer::class, 'https://example.com/landing');

        // Hot path: the row already exists, so a single SELECT resolves it.
        $this->assertCount(1, DB::getQueryLog());
        $this->assertSame($first, $second);
        $this->assertDatabaseCount('referrers', 1);
    }

    public function test_new_value_is_inserted_and_returns_its_id(): void
    {
        $resolver = new DictionaryValueResolver;

        DB::enableQueryLog();
        DB::flushQueryLog();

        $id = $resolver->resolveId(Referrer::class, 'https://example.com/landing');

        // Miss: one SELECT (not found) + one INSERT ... RETURNING.
        $this->assertCount(2, DB::getQueryLog());
        $this->assertNotNull($id);
        $this->assertDatabaseCount('referrers', 1);
        $this->assertSame($id, (int) DB::table('referrers')->where('value', 'https://example.com/landing')->value('id'));
    }

    public function test_null_value_resolves_to_null_without_querying(): void
    {
        $resolver = new DictionaryValueResolver;

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->assertNull($resolver->resolveId(Referrer::class, null));
        $this->assertCount(0, DB::getQueryLog());
        $this->assertDatabaseCount('referrers', 0);
    }

    public function test_resolves_inet_typed_dictionary_value(): void
    {
        $resolver = new DictionaryValueResolver;

        $id = $resolver->resolveId(IpAddress::class, '203.0.113.9');

        $this->assertNotNull($id);
        $this->assertSame($id, $resolver->resolveId(IpAddress::class, '203.0.113.9'));
        $this->assertDatabaseHas('ip_addresses', ['value' => '203.0.113.9']);
        $this->assertDatabaseCount('ip_addresses', 1);
    }
}
