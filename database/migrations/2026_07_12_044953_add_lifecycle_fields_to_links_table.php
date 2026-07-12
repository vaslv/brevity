<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->dropColumn(['valid_since', 'valid_until', 'max_clicks']);
        });
    }

    /**
     * Link lifecycle limits (docs/07-plans.md §4): a link is alive only while
     * valid_since is not in the future, valid_until is not in the past and the
     * click-counter sum stays below max_clicks. All three are optional and
     * nullable — NULL means "no limit".
     */
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->timestampTz('valid_since')->nullable();
            $table->timestampTz('valid_until')->nullable();
            $table->integer('max_clicks')->nullable();
        });
    }
};
