<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('domain_domain_group');
    }

    public function up(): void
    {
        Schema::create('domain_domain_group', function (Blueprint $table) {
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_group_id')->constrained()->cascadeOnDelete();

            // A domain belongs to a group at most once; the composite primary key
            // also serves as the lookup index for "groups of a domain"
            // (domain_id leading).
            $table->primary(['domain_id', 'domain_group_id']);

            // Reverse-direction lookup ("domains of a group") needs its own index
            // since the composite primary key only covers the domain_id prefix.
            $table->index('domain_group_id');
        });
    }
};
