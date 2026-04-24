<?php

namespace App\Filament\Resources\Settings\Pages;

use App\Filament\Resources\Settings\SettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ListSettings extends ListRecords
{
    protected static string $resource = SettingResource::class;

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make(__('resources/setting.tabs.all')),

            'general' => Tab::make(__('resources/setting.tabs.general'))
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->whereNull('group')
                ),
        ];

        foreach (setting()->groups() as $group) {
            $tabs[$group] = Tab::make(Str::title($group))
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->where('group', $group)
                );
        }

        return $tabs;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
