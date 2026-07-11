<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('link_click_counters');
    }

    /**
     * Slotted pre-aggregated click counters (docs/07-plans.md §3): the random
     * slot (1..100) spreads concurrent UPSERT increments across rows so parallel
     * click recording never contends on a single hot row. A link's total is the
     * SUM over its slots; the unique index also serves per-link lookups.
     */
    public function up(): void
    {
        Schema::create('link_click_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->constrained('links')->restrictOnDelete();
            $table->boolean('is_bot');
            $table->smallInteger('slot');
            $table->bigInteger('count')->default(0);

            $table->unique(['link_id', 'is_bot', 'slot']);
        });

        // DB-level guards: the app only ever writes slots 1..100 and positive
        // counts, but the invariants are cheap to enforce against manual or
        // future writers.
        DB::statement('alter table link_click_counters add constraint link_click_counters_slot_check check (slot between 1 and 100)');
        DB::statement('alter table link_click_counters add constraint link_click_counters_count_check check (count >= 0)');
    }
};
