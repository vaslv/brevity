<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS domains_is_default_unique');

        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->boolean('is_default')->default(false);
        });

        // Partial unique index: at most one domain may be the default. Only
        // `true` rows are indexed, so any second default insert/update fails
        // at the DB level rather than silently producing two defaults.
        DB::statement('CREATE UNIQUE INDEX domains_is_default_unique ON domains (is_default) WHERE is_default');
    }
};
