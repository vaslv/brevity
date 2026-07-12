<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('rules', function (Blueprint $table) {
            $table->foreignId('condition_id')->nullable()->constrained('conditions')->restrictOnDelete();
        });

        // Restore the single-condition column from the pivot (first condition
        // per rule). Multi-condition rules lose their extra conditions — the
        // old schema cannot represent them.
        DB::statement(<<<'SQL'
            update rules set condition_id = (
                select condition_id from condition_rule
                where condition_rule.rule_id = rules.id
                order by condition_id limit 1
            )
        SQL);

        Schema::dropIfExists('condition_rule');
    }

    /**
     * Multi-condition rules (RUL-01): a rule matches when ALL its conditions
     * match (AND); an empty set is unconditional. Replaces the single
     * rules.condition_id with a many-to-many pivot.
     */
    public function up(): void
    {
        Schema::create('condition_rule', function (Blueprint $table) {
            $table->foreignId('rule_id')->constrained('rules')->cascadeOnDelete();
            $table->foreignId('condition_id')->constrained('conditions')->restrictOnDelete();

            $table->unique(['rule_id', 'condition_id']);
            // The unique index leads with rule_id and cannot serve the
            // condition-side restrict check on a conditions delete.
            $table->index('condition_id');
        });

        DB::statement(<<<'SQL'
            insert into condition_rule (rule_id, condition_id)
            select id, condition_id from rules where condition_id is not null
        SQL);

        Schema::table('rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('condition_id');
        });
    }
};
