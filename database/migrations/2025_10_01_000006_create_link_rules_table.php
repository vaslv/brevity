<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('link_url');
    }

    public function up(): void
    {
        Schema::create('link_url', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->constrained('links')->cascadeOnDelete();
            $table->foreignId('condition_id')->nullable()->constrained('conditions')->restrictOnDelete();
            $table->foreignId('url_id')->constrained('urls')->restrictOnDelete();
            $table->integer('priority')->default(0);
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['link_id', 'priority']);
        });
    }
};
