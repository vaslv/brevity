<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('links');
    }

    public function up(): void
    {
        Schema::create('links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->foreignId('domain_id')->nullable()->constrained('domains')->restrictOnDelete();
            $table->string('code', 8)->nullable();
            $table->string('title', 64)->nullable();
            $table->boolean('forward_query')->default(false);
            $table->jsonb('callback_data')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->softDeletesTz();

            $table->unique(['domain_id', 'code']);
        });
    }
};
