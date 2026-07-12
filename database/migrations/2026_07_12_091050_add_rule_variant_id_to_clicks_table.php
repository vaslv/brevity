<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('clicks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rule_variant_id');
        });
    }

    /**
     * Records which A/B variant a click resolved to (GAP-04) so partners can
     * see the split in analytics and callbacks. nullOnDelete: a rewritten rule
     * set drops its old variants, but the historical click must survive.
     */
    public function up(): void
    {
        Schema::table('clicks', function (Blueprint $table) {
            // Indexed: powers per-variant analytics and the ON DELETE SET NULL
            // sweep when a rule-set rewrite drops variants.
            $table->foreignId('rule_variant_id')->nullable()->after('url_id')
                ->constrained('rule_variants')->nullOnDelete()->index();
        });
    }
};
