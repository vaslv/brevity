<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('domain_groups', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }

    public function up(): void
    {
        // Machine name used to reference the group from the API (the ?group=
        // domain filter and domain_group on link creation). Added nullable
        // first so existing rows can be backfilled before the NOT NULL + UNIQUE
        // constraints land.
        Schema::table('domain_groups', function (Blueprint $table) {
            $table->string('code', 255)->nullable()->after('name');
        });

        $this->backfillCodes();

        Schema::table('domain_groups', function (Blueprint $table) {
            $table->string('code', 255)->nullable(false)->change();
            $table->unique('code');
        });
    }

    /**
     * Seed each existing group with a unique code derived from its name.
     */
    private function backfillCodes(): void
    {
        $used = [];

        foreach (DB::table('domain_groups')->orderBy('id')->get(['id', 'name']) as $group) {
            $base = Str::slug((string) $group->name) ?: 'group';
            $code = $base;
            $suffix = 1;

            while (in_array($code, $used, true)) {
                $code = $base.'-'.(++$suffix);
            }

            $used[] = $code;

            DB::table('domain_groups')->where('id', $group->id)->update(['code' => $code]);
        }
    }
};
