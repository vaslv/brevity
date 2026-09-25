<?php

use App\Services\Links\Domains\DomainName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Canonicalization preserves domain identity but cannot restore the
        // original spelling/case. Keep canonical values when rolling back.
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $domains = DB::table('domains')->orderBy('id')->lockForUpdate()->get(['id', 'value']);
            $normalized = [];
            $owners = [];
            $problems = [];

            foreach ($domains as $domain) {
                $ascii = DomainName::tryToAscii($domain->value);

                if ($ascii === null) {
                    $problems[] = "Invalid domain ID {$domain->id}";

                    continue;
                }

                if (isset($owners[$ascii])) {
                    $problems[] = "Domain IDs {$owners[$ascii]} and {$domain->id} normalize to {$ascii}";
                }

                $owners[$ascii] = $domain->id;
                $normalized[$domain->id] = $ascii;
            }

            if ($problems !== []) {
                throw new RuntimeException('Domain normalization aborted; no records changed. '.implode('; ', $problems));
            }

            foreach ($domains as $domain) {
                if ($domain->value !== $normalized[$domain->id]) {
                    DB::table('domains')->where('id', $domain->id)->update(['value' => $normalized[$domain->id]]);
                }
            }
        });
    }
};
