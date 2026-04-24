<?php

namespace App\Filament\Widgets;

use App\Models\Click;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class ClicksChart extends ChartWidget
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '240px';

    protected static ?int $sort = 2;

    public function getHeading(): ?string
    {
        return __('widgets.clicks_chart.heading');
    }

    protected function getData(): array
    {
        $days = 14;
        $start = Carbon::today()->subDays($days - 1);

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
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.2)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
