<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('domain_groups');
    }

    public function up(): void
    {
        Schema::create('domain_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->unique();
            $table->timestampsTz();
        });
    }
};
