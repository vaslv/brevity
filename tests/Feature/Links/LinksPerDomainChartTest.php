<?php

namespace Tests\Feature\Links;

use App\Filament\Widgets\LinksPerDomainChart;
use App\Models\Domain;
use App\Models\Link;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Guards the dashboard links-per-domain bar chart: one bar per domain ordered
 * by link count (tallest first), capped at 20 domains, counts honouring the
 * Link soft-delete scope, domainless links excluded, and a distinct, stable
 * color per domain.
 */
class LinksPerDomainChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigns_a_distinct_translucent_color_per_domain(): void
    {
        Domain::factory()->count(3)->create();

        $dataset = $this->chartData()['datasets'][0];

        $this->assertCount(3, $dataset['backgroundColor']);
        $this->assertSame($dataset['backgroundColor'], array_unique($dataset['backgroundColor']));

        foreach ($dataset['backgroundColor'] as $fill) {
            $this->assertMatchesRegularExpression('/^hsla\(\d+, 65%, 55%, 0\.5\)$/', $fill);
        }

        foreach ($dataset['borderColor'] as $border) {
            $this->assertMatchesRegularExpression('/^hsl\(\d+, 65%, 55%\)$/', $border);
        }
    }

    public function test_caps_the_chart_at_twenty_domains(): void
    {
        Domain::factory()->count(21)->create();

        $data = $this->chartData();

        $this->assertCount(20, $data['labels']);
        $this->assertCount(20, $data['datasets'][0]['data']);
    }

    public function test_counts_links_per_domain_ordered_by_count_desc(): void
    {
        $busy = Domain::factory()->create(['value' => 'busy.test']);
        $quiet = Domain::factory()->create(['value' => 'quiet.test']);
        Domain::factory()->create(['value' => 'empty.test']);

        Link::factory()->count(3)->forDomain($busy)->create();
        Link::factory()->forDomain($quiet)->create();

        // Domainless link (resolves via config('app.url')) — not attached to a row.
        Link::factory()->create();

        $data = $this->chartData();

        $this->assertSame(['busy.test', 'quiet.test', 'empty.test'], $data['labels']);
        $this->assertSame([3, 1, 0], $data['datasets'][0]['data']);
    }

    public function test_domain_color_is_stable_across_renders(): void
    {
        Domain::factory()->count(3)->create();

        $this->assertSame(
            $this->chartData()['datasets'][0]['backgroundColor'],
            $this->chartData()['datasets'][0]['backgroundColor'],
        );
    }

    public function test_excludes_soft_deleted_links(): void
    {
        $domain = Domain::factory()->create(['value' => 'only.test']);
        Link::factory()->count(2)->forDomain($domain)->create();
        Link::factory()->forDomain($domain)->create()->delete();

        $this->assertSame([2], $this->chartData()['datasets'][0]['data']);
    }

    /**
     * @return array{datasets: list<array<string, mixed>>, labels: list<string>}
     */
    private function chartData(): array
    {
        $getData = new ReflectionMethod(LinksPerDomainChart::class, 'getData');

        return $getData->invoke(new LinksPerDomainChart);
    }
}
