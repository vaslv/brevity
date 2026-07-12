<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('geo_locations');
    }

    /**
     * Click geolocation dictionary (stage 4): a click's IP resolves to a
     * (country, region, city) tuple, deduplicated here. region/city are ''
     * (never NULL) when unknown — Postgres treats NULLs as distinct in a UNIQUE
     * index, so a NULL tuple would insert repeatedly and defeat the dictionary.
     */
    public function up(): void
    {
        Schema::create('geo_locations', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2); // ISO 3166-1 alpha-2
            $table->string('region', 128)->default('');
            $table->string('city', 128)->default('');
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['country_code', 'region', 'city']);
        });
    }
};
