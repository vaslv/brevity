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
            // Postgres does not index referencing FKs automatically; the rule
            // index backs the cascade delete on a rule-set rewrite.
            $table->foreignId('rule_id')->constrained('rules')->cascadeOnDelete()->index();
            $table->foreignId('url_id')->constrained('urls')->restrictOnDelete();
            $table->smallInteger('weight');
            $table->string('label', 64)->nullable();
        });

        DB::statement('alter table rule_variants add constraint rule_variants_weight_check check (weight >= 1)');
    }
};
