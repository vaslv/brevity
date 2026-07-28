<?php

namespace App\Filament\Pages;

use App\Filament\Support\DashboardPeriod;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

/**
 * The stock dashboard plus a page-level period selector: every widget that
 * uses InteractsWithPageFilters (the stat cards) reacts to the chosen preset.
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('period')
                ->label(__('dashboard.periods.label'))
                ->options(DashboardPeriod::options())
                ->default(DashboardPeriod::DEFAULT)
                ->selectablePlaceholder(false),
        ]);
    }
}
