<?php

namespace App\Filament\Widgets;

use App\Models\Click;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class ClicksChart extends ChartWidget
{
    /** @var list<int> */
    private const array PERIOD_DAYS = [30, 60, 90];

    public ?string $filter = '30';

    /**
     * Follow the panel palette instead of hardcoding a hex pair, so a theme
     * change (e.g. the primary color) restyles the chart automatically.
     */
    protected string $color = 'primary';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '240px';

    protected static ?int $sort = 2;

    public function getHeading(): ?string
    {
        return __('widgets.clicks_chart.heading', ['days' => $this->periodDays()]);
    }

    protected function getData(): array
    {
        $days = $this->periodDays();
        $start = Carbon::today()->subDays($days - 1);

        // Grouped by calendar day in the DB session timezone, which matches the
        // app timezone (UTC) — so chart days line up with the labels below.
        // Revisit `date(created_at)` if a per-user timezone is ever introduced.
        $rows = Click::query()
            ->selectRaw('date(created_at) as day, count(*) as total')
            ->where('created_at', '>=', $start)
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $values = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            $key = $date->toDateString();
            $labels[] = $date->format('d.m');
            $values[] = (int) ($rows[$key] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => __('widgets.clicks_chart.dataset'),
                    'data' => $values,
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * PHP coerces the numeric string keys to int; Livewire round-trips the
     * selected one back into `$filter` as a string.
     *
     * @return array<int, string>
     */
    protected function getFilters(): ?array
    {
        $filters = [];

        foreach (self::PERIOD_DAYS as $days) {
            $filters[$days] = (string) __('widgets.clicks_chart.filter', ['days' => $days]);
        }

        return $filters;
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * The selected period, guarded against unexpected filter values.
     */
    private function periodDays(): int
    {
        $days = (int) $this->filter;

        return in_array($days, self::PERIOD_DAYS, true) ? $days : self::PERIOD_DAYS[0];
    }
}
