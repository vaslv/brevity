<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('clicks', function (Blueprint $table) {
            $table->dropIndex('clicks_geo_location_id_index');
            $table->dropConstrainedForeignId('geo_location_id');
        });
    }

    /**
     * Records the geolocation a click resolved to (stage 4). Nullable: geo
     * resolution runs in the async job and may yield nothing (no database, an
     * unknown or pruned IP) — a click without a location is normal, never an
     * error. restrictOnDelete like the other click dictionaries; geo rows are
     * never pruned (no PII, a bounded set).
     */
    public function up(): void
    {
        Schema::table('clicks', function (Blueprint $table) {
            $table->foreignId('geo_location_id')->nullable()->after('rule_variant_id')
                ->constrained('geo_locations')->restrictOnDelete();
        });

        // Partial index: geo is added now, so the vast majority of historical
        // clicks carry a NULL geo_location_id — indexing them wastes space. The
        // predicate is implied by `geo_location_id = $1`, so this still serves
        // both the FK restrict check on a geo_locations delete and per-country
        // analytics. A separate DB::statement on purpose (Blueprint has no
        // partial-index option, and chaining ->index() onto ->constrained()
        // sets the FK constraint name rather than creating an index — review
        // 2026-07-12 — r1).
        DB::statement(<<<'SQL'
            create index clicks_geo_location_id_index
                on clicks (geo_location_id) where geo_location_id is not null
            SQL);
    }
};
