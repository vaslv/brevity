<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('clicks', function (Blueprint $table) {
            $table->dropIndex('clicks_service_id_created_at_index');
        });

        Schema::table('callbacks', function (Blueprint $table) {
            $table->dropIndex('callbacks_service_id_created_at_index');
        });
    }

    public function up(): void
    {
        Schema::table('clicks', function (Blueprint $table) {
            $table->index(['service_id', 'created_at'], 'clicks_service_id_created_at_index');
        });

        Schema::table('callbacks', function (Blueprint $table) {
            $table->index(['service_id', 'created_at'], 'callbacks_service_id_created_at_index');
        });
    }
};
