<?php

namespace Tests\Feature\Regressions;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression for docs/AUDIT_2026-06.md — H5.
 *
 * Postgres does not auto-index FK columns, and links had indexes only on id and
 * code. Service-scoped queries and restrictOnDelete reverse lookups on
 * service_id/domain_id were sequential scans. Both FK columns must be indexed.
 */
class LinkForeignKeyIndexesTest extends TestCase
{
    use RefreshDatabase;

    public function test_links_foreign_key_columns_are_indexed(): void
    {
        $defs = collect(DB::select(
            "SELECT indexdef FROM pg_indexes WHERE tablename = 'links'"
        ))->map(fn (object $row): string => $row->indexdef);

        $this->assertTrue(
            $defs->contains(fn (string $def): bool => str_contains($def, '(service_id)')),
            'links.service_id is not indexed',
        );

        $this->assertTrue(
            $defs->contains(fn (string $def): bool => str_contains($def, '(domain_id)')),
            'links.domain_id is not indexed',
        );
    }
}
