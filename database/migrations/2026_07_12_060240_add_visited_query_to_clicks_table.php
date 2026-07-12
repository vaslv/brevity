<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('clicks', function (Blueprint $table) {
            $table->dropColumn('visited_query');
        });
    }

    /**
     * The visit's own query string (GAP-03): partners get their sub-ids back
     * via {{click.query.<param>}} callback placeholders. Raw storage, no
     * dictionary — query strings barely repeat.
     */
    public function up(): void
    {
        Schema::table('clicks', function (Blueprint $table) {
            $table->text('visited_query')->nullable();
        });
    }
};
