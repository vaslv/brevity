<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('callbacks', function (Blueprint $table) {
            $table->dropUnique(['click_id']);
        });
    }

    /**
     * Enforce one callback per click at the database level. CallbackDispatcher's
     * firstOrCreate(['click_id' => ...]) is only race-safe with this unique
     * index — without it, concurrent RecordClickJob runs for the same click
     * could insert two callbacks and enqueue duplicate webhooks. Assumes no
     * existing duplicate click_id rows.
     */
    public function up(): void
    {
        Schema::table('callbacks', function (Blueprint $table) {
            $table->unique('click_id');
        });
    }
};
