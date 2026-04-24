<?php

namespace App\Filament\Widgets;

use App\Models\Click;
use App\Models\Link;
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

        return [
            Stat::make(
                __('widgets.stats.links_total'),
                Link::query()->count(),
            ),
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
