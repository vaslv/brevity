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
            $table->dropIndex(['user_agent_id']);
            $table->dropIndex(['ip_address_id']);
            $table->dropIndex('clicks_pending_ip_created_at_index');
        });
    }

    /**
     * Postgres does not index referencing FK columns automatically. Without
     * these indexes the admin bot filter (whereHas userAgent.is_bot), the FK
     * restrict-check on user_agents deletion and the per-row RI probe fired by
     * every ip_addresses delete in ips:prune all scan the whole clicks table.
     */
    public function up(): void
    {
        Schema::table('clicks', function (Blueprint $table) {
            $table->index('user_agent_id');
            $table->index('ip_address_id');
        });

        // ips:prune's detach phase filters `created_at < cutoff AND
        // ip_address_id IS NOT NULL`; a partial index keeps each daily run
        // proportional to the day's new clicks and shrinks as rows are
        // detached, instead of rescanning the whole pre-cutoff history.
        DB::statement(<<<'SQL'
            create index clicks_pending_ip_created_at_index
                on clicks (created_at) where ip_address_id is not null
            SQL);
    }
};
