<?php

namespace App\Filament\Widgets;

use App\Filament\Support\DashboardPeriod;
use App\Models\Callback;
use App\Models\Click;
use App\Models\Link;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

/**
 * Period-scoped dashboard cards (links, clicks, callbacks) driven by the
 * page-level period selector. Clicks count everything, bots included. Each
 * card compares against the previous comparable window resolved by
 * DashboardPeriod (an in-progress period is cut at the same elapsed time).
 */
class StatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        [$from, $to, $previousFrom, $previousTo] = DashboardPeriod::windows($this->pageFilters ?? []);

        return [
            $this->periodStat(__('widgets.stats.links'), Link::class, $from, $to, $previousFrom, $previousTo),
            $this->periodStat(__('widgets.stats.clicks'), Click::class, $from, $to, $previousFrom, $previousTo),
            $this->periodStat(__('widgets.stats.callbacks'), Callback::class, $from, $to, $previousFrom, $previousTo),
        ];
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function countBetween(string $model, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $model::query()
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->count();
    }

    /**
     * A created_at count over the selected window, described by the change
     * against the previous comparable window.
     *
     * @param  class-string<Model>  $model
     */
    private function periodStat(
        string $label,
        string $model,
        CarbonImmutable $from,
        CarbonImmutable $to,
        CarbonImmutable $previousFrom,
        CarbonImmutable $previousTo,
    ): Stat {
        $current = $this->countBetween($model, $from, $to);
        $previous = $this->countBetween($model, $previousFrom, $previousTo);

        $stat = Stat::make($label, $current);

        if ($previous === 0) {
            return $stat
                ->description(__('widgets.stats.previous_period_empty'))
                ->color('gray');
        }

        $percent = round(($current - $previous) * 100 / $previous, 1);
        $change = ($percent > 0 ? '+' : '').rtrim(rtrim(number_format($percent, 1, '.', ''), '0'), '.').'%';

        [$icon, $color] = match (true) {
            $percent > 0 => [Heroicon::ArrowTrendingUp, 'success'],
            $percent < 0 => [Heroicon::ArrowTrendingDown, 'danger'],
            default => [Heroicon::Minus, 'gray'],
        };

        return $stat
            ->description(__('widgets.stats.vs_previous_period', ['change' => $change, 'previous' => $previous]))
            ->descriptionIcon($icon)
            ->color($color);
    }
}
