<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('geo_locations', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }

    /**
     * City coordinates for the clicks geo map widget. Nullable: tuples created
     * before this migration (or resolved from a database record without
     * coordinates) have none until geo:backfill-coordinates fills them in.
     * decimal(10,7) covers ±90/±180 at MaxMind's precision.
     */
    public function up(): void
    {
        Schema::table('geo_locations', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
        });
    }
};
