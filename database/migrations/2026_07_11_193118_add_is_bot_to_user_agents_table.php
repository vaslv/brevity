<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('user_agents', function (Blueprint $table) {
            $table->dropColumn('is_bot');
        });
    }

    public function up(): void
    {
        Schema::table('user_agents', function (Blueprint $table) {
            $table->boolean('is_bot')->default(false);
        });
    }
};
