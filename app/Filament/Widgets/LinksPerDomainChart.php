<?php

namespace App\Filament\Widgets;

use App\Models\Domain;
use Filament\Widgets\ChartWidget;

class LinksPerDomainChart extends ChartWidget
{
    /**
     * Cap the bars so the chart stays readable; only the busiest domains show.
     */
    private const MAX_DOMAINS = 20;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '240px';

    protected static ?int $sort = 3;

    public function getHeading(): ?string
    {
        return __('widgets.links_per_domain_chart.heading');
    }

    protected function getData(): array
    {
        // Top domains by link count, tallest first. `withCount('links')` counts
        // via the `domain_id` foreign key and honours the Link soft-delete scope,
        // so it matches the StatsOverview links total. Links created without a
        // domain (resolved via config('app.url')) belong to no row and are not
        // charted here.
        $domains = Domain::query()
            ->withCount('links')
            ->orderByDesc('links_count')
            ->orderBy('value')
            ->limit(self::MAX_DOMAINS)
            ->get();

        $hues = $domains->map(fn (Domain $domain): int => $this->hueForDomain($domain));

        return [
            'datasets' => [
                [
                    'label' => __('widgets.links_per_domain_chart.dataset'),
                    'data' => $domains->map(fn (Domain $domain): int => (int) $domain->links_count)->all(),
                    // Translucent fill under a solid same-hue border, mirroring the
                    // filled area of the clicks chart above.
                    'backgroundColor' => $hues->map(fn (int $hue): string => "hsla({$hue}, 65%, 55%, 0.5)")->all(),
                    'borderColor' => $hues->map(fn (int $hue): string => "hsl({$hue}, 65%, 55%)")->all(),
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $domains->pluck('value')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * A stable, well-spread hue per domain. The golden-angle hue is keyed on the
     * domain id, so a domain keeps the same color even as link counts (and
     * therefore bar order) shift between loads.
     */
    private function hueForDomain(Domain $domain): int
    {
        return (int) round(fmod($domain->id * 137.508, 360));
    }
}
