<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('clicks', function (Blueprint $table) {
            $table->dropIndex('clicks_created_at_index');
        });
    }

    public function up(): void
    {
        // Dashboard widgets and the sidebar badge filter clicks by created_at
        // alone; the existing composite indexes lead with service_id/link_id and
        // can't serve those scans. A standalone index keeps them off a seq scan.
        Schema::table('clicks', function (Blueprint $table) {
            $table->index('created_at', 'clicks_created_at_index');
        });
    }
};
