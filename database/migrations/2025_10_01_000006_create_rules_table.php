<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('rules');
    }

    public function up(): void
    {
        Schema::create('rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->constrained('links')->cascadeOnDelete();
            $table->foreignId('url_id')->constrained('urls')->restrictOnDelete();
            $table->foreignId('condition_id')->nullable()->constrained('conditions')->restrictOnDelete();
            $table->string('transition_mode', 16)->nullable();
            $table->integer('priority')->default(0);
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['link_id', 'priority']);
        });
    }
};
