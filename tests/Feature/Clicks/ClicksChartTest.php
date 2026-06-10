<?php

namespace Tests\Feature\Clicks;

use App\Filament\Widgets\ClicksChart;
use App\Models\Click;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Guards the dashboard clicks chart: a 14-day window bucketed by calendar day,
 * and no hardcoded dataset colors — the chart must follow the panel palette
 * via the widget's `$color` (so a theme change restyles it automatically).
 */
class ClicksChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_buckets_clicks_by_day_within_the_14_day_window(): void
    {
        Click::factory()->count(2)->create();

        $old = Click::factory()->create();
        Click::query()->whereKey($old->id)->update(['created_at' => now()->subDays(2)]);

        $outside = Click::factory()->create();
        Click::query()->whereKey($outside->id)->update(['created_at' => now()->subDays(20)]);

        $data = $this->chartData();
        $values = $data['datasets'][0]['data'];

        $this->assertCount(14, $data['labels']);
        $this->assertCount(14, $values);
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

    /**
     * @return array{datasets: list<array<string, mixed>>, labels: list<string>}
     */
    private function chartData(): array
    {
        $getData = new ReflectionMethod(ClicksChart::class, 'getData');

        return $getData->invoke(new ClicksChart);
    }
}
