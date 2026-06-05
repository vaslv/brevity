<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->string('code', 8)->nullable()->change();
        });
    }

    /**
     * Widen links.code from varchar(8) to varchar(16). Hashids encodes the link
     * id (alphabet of 52, min length 5); the code reaches 9 chars at id ~52.5B
     * and up to 14 chars at the bigint ceiling, which would otherwise overflow
     * varchar(8) and throw mid-create. The matching route constraint in
     * routes/web.php is widened to {5,16} in lock-step so longer codes resolve.
     */
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->string('code', 16)->nullable()->change();
        });
    }
};
