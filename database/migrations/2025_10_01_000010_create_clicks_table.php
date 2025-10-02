<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('clicks');
    }

    public function up(): void
    {
        Schema::create('clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->foreignId('link_id')->constrained('links')->restrictOnDelete();
            $table->foreignId('url_id')->constrained('urls')->restrictOnDelete();
            $table->foreignId('referrer_id')->constrained('referrers')->restrictOnDelete();
            $table->foreignId('user_agent_id')->constrained('user_agents')->restrictOnDelete();
            $table->foreignId('ip_address_id')->constrained('ip_addresses')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
        });
    }
};
