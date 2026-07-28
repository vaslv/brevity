<?php

namespace Tests\Feature\Clicks;

use App\Filament\Widgets\ClicksChart;
use App\Models\Click;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Guards the dashboard clicks chart: a selectable 30/60/90-day window (30 by
 * default) bucketed by calendar day, and no hardcoded dataset colors — the
 * chart must follow the panel palette via the widget's `$color` (so a theme
 * change restyles it automatically).
 */
class ClicksChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_buckets_clicks_by_day_within_the_default_30_day_window(): void
    {
        Click::factory()->count(2)->create();

        $old = Click::factory()->create();
        Click::query()->whereKey($old->id)->update(['created_at' => now()->subDays(2)]);

        $outside = Click::factory()->create();
        Click::query()->whereKey($outside->id)->update(['created_at' => now()->subDays(40)]);

        $data = $this->chartData();
        $values = $data['datasets'][0]['data'];

        $this->assertCount(30, $data['labels']);
        $this->assertCount(30, $values);
        $this->assertSame(2, end($values));
        $this->assertSame(3, array_sum($values));
    }

    public function test_dataset_carries_no_hardcoded_colors(): void
    {
        $dataset = $this->chartData()['datasets'][0];

        $this->assertArrayNotHasKey('borderColor', $dataset);
        $this->assertArrayNotHasKey('backgroundColor', $dataset);
        $this->assertSame('primary', (new ClicksChart)->getColor());
    }

    public function test_filter_widens_the_window_to_the_selected_period(): void
    {
        $outside = Click::factory()->create();
        Click::query()->whereKey($outside->id)->update(['created_at' => now()->subDays(40)]);

        $data = $this->chartData(filter: '60');

        $this->assertCount(60, $data['labels']);
        $this->assertSame(1, array_sum($data['datasets'][0]['data']));

        $this->assertCount(90, $this->chartData(filter: '90')['labels']);
    }

    public function test_offers_the_period_filters(): void
    {
        $getFilters = new ReflectionMethod(ClicksChart::class, 'getFilters');

        $this->assertSame([30, 60, 90], array_keys($getFilters->invoke(new ClicksChart)));
    }

    public function test_unknown_filter_falls_back_to_the_default_period(): void
    {
        $this->assertCount(30, $this->chartData(filter: '7')['labels']);
    }

    /**
     * @return array{datasets: list<array<string, mixed>>, labels: list<string>}
     */
    private function chartData(?string $filter = null): array
    {
        $widget = new ClicksChart;

        if ($filter !== null) {
            $widget->filter = $filter;
        }

        $getData = new ReflectionMethod(ClicksChart::class, 'getData');

        return $getData->invoke($widget);
    }
}
