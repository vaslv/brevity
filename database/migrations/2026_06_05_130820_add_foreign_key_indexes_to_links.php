<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->dropIndex(['service_id']);
            $table->dropIndex(['domain_id']);
        });
    }

    /**
     * Postgres does not auto-index foreign-key columns. Without these, every
     * service-scoped link query and every restrictOnDelete reverse lookup
     * (deleting a Service/Domain checks links by service_id/domain_id) does a
     * sequential scan that degrades as the links table grows.
     */
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->index('service_id');
            $table->index('domain_id');
        });
    }
};
