<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('callbacks');
    }

    public function up(): void
    {
        Schema::create('callbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('click_id')->constrained('clicks')->cascadeOnDelete();
            $table->jsonb('data');
            $table->integer('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->string('status', 32)->default('pending');
            $table->integer('attempts')->default(0);
            $table->timestampTz('last_attempt_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('status');
        });
    }
};
