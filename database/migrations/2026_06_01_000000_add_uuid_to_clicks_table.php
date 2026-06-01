<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('clicks', function (Blueprint $table) {
            $table->dropUnique('clicks_uuid_unique');
            $table->dropColumn('uuid');
        });
    }

    public function up(): void
    {
        // Idempotency key for RecordClickJob: a click is generated once per
        // resolve and carried through every job retry, so re-execution reuses
        // the same row instead of duplicating clicks/callbacks.
        // Nullable so existing rows stay valid; Postgres allows many NULLs
        // under a unique index.
        Schema::table('clicks', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique();
        });
    }
};
