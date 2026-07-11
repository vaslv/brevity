<?php

namespace App\Filament\Widgets;

use App\Models\Click;
use App\Models\Link;
use App\Models\LinkClickCounter;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $today = Carbon::today();
        $weekAgo = Carbon::today()->subDays(6);

        // All-time totals come from the pre-aggregated counters (instant at any
        // volume); the time-windowed cards stay on the indexed created_at range
        // scans — the counters carry no time dimension by design.
        $totalClicks = (int) LinkClickCounter::query()->sum('count');
        $nonBotClicks = (int) LinkClickCounter::query()->where('is_bot', false)->sum('count');

        return [
            Stat::make(
                __('widgets.stats.links_total'),
                Link::query()->count(),
            ),
            Stat::make(
                __('widgets.stats.clicks_total'),
                $totalClicks,
            )->description(__('widgets.stats.clicks_total_non_bots', ['count' => $nonBotClicks])),
            Stat::make(
                __('widgets.stats.clicks_today'),
                Click::query()->where('created_at', '>=', $today)->count(),
            ),
            Stat::make(
                __('widgets.stats.clicks_week'),
                Click::query()->where('created_at', '>=', $weekAgo)->count(),
            ),
        ];
    }
}
