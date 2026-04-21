<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS conditions_type_data_unique');
    }

    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX conditions_type_data_unique ON conditions (type, data)');
    }
};
