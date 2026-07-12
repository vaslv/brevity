<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('rule_variants');
    }

    /**
     * A/B split (GAP-04): a rule may fan its winning traffic across weighted
     * target URLs. Without variants a rule uses rules.url_id as before; with
     * variants the target is chosen by weight, sticky per visitor.
     */
    public function up(): void
    {
        Schema::create('rule_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('rules')->cascadeOnDelete();
            $table->foreignId('url_id')->constrained('urls')->restrictOnDelete();
            $table->smallInteger('weight');
            $table->string('label', 64)->nullable();

            // Postgres does not index referencing FKs automatically; this index
            // backs the cascade delete on a rule-set rewrite. A separate call on
            // purpose: chaining ->index() onto ->constrained() sets the FK
            // constraint NAME and creates no index (review 2026-07-12 — r1).
            $table->index('rule_id');
        });

        DB::statement('alter table rule_variants add constraint rule_variants_weight_check check (weight >= 1)');
    }
};
