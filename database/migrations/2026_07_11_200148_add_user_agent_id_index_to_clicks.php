<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('clicks', function (Blueprint $table) {
            $table->dropIndex(['user_agent_id']);
        });
    }

    /**
     * Postgres does not index referencing FK columns automatically. Without
     * this index the admin bot filter (whereHas userAgent.is_bot) and the FK
     * restrict-check on user_agents deletion both scan the whole clicks table.
     */
    public function up(): void
    {
        Schema::table('clicks', function (Blueprint $table) {
            $table->index('user_agent_id');
        });
    }
};
