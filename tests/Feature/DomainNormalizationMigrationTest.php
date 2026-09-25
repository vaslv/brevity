<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainGroup;
use App\Models\Link;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DomainNormalizationMigrationTest extends TestCase
{
    use RefreshDatabase;

    public static function conflictingNames(): array
    {
        return [
            ['пример.рф', 'xn--e1afmkfd.xn--p1ai'],
            ['Example.com', 'example.com.'],
            ['пример.рф', 'https://invalid.test'],
            ['пример.рф', 'xn--a.test'],
        ];
    }

    public function test_migration_preserves_ids_links_groups_and_default(): void
    {
        $domain = Domain::factory()->asDefault()->create();
        $createdAt = $domain->refresh()->created_at;
        $link = Link::factory()->forDomain($domain)->create();
        $group = DomainGroup::factory()->create();
        $group->domains()->attach($domain);
        DB::table('domains')->where('id', $domain->id)->update(['value' => ' ПРИМЕР.РФ. ']);
        $ascii = Domain::factory()->create();
        DB::table('domains')->where('id', $ascii->id)->update(['value' => 'EXAMPLE.COM.']);

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame('xn--e1afmkfd.xn--p1ai', $domain->refresh()->value);
        $this->assertSame('example.com', $ascii->refresh()->value);
        $this->assertTrue($domain->is_default);
        $this->assertSame($createdAt, $domain->created_at);
        $this->assertSame($domain->id, $link->refresh()->domain_id);
        $this->assertSame([$domain->id], $group->domains()->pluck('domains.id')->all());
        $this->assertSame(2, Domain::query()->count());

        $this->migration()->down();
        $this->assertSame('xn--e1afmkfd.xn--p1ai', $domain->refresh()->value);
    }

    #[DataProvider('conflictingNames')]
    public function test_preflight_leaves_all_records_unchanged(string $first, string $second): void
    {
        $valid = Domain::factory()->create();
        $one = Domain::factory()->create();
        $two = Domain::factory()->create();
        DB::table('domains')->where('id', $valid->id)->update(['value' => 'BÜCHER.DE']);
        DB::table('domains')->where('id', $one->id)->update(['value' => $first]);
        DB::table('domains')->where('id', $two->id)->update(['value' => $second]);
        $before = DB::table('domains')->orderBy('id')->get()->toJson();

        try {
            $this->migration()->up();
            $this->fail('Migration should reject invalid or duplicate names.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString((string) $two->id, $exception->getMessage());
            $this->assertStringContainsString('no records changed', $exception->getMessage());
        }

        $this->assertSame($before, DB::table('domains')->orderBy('id')->get()->toJson());
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_09_25_094215_normalize_existing_domain_names.php');
    }
}
