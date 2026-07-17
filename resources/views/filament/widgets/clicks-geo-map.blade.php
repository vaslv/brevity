<x-filament-widgets::widget>
    <x-filament::section
        :heading="__('widgets.clicks_geo_map.heading', ['days' => \App\Filament\Widgets\ClicksGeoMap::DAYS])"
    >
        @php($markers = $this->getMarkers())

        @if ($markers === [])
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('widgets.clicks_geo_map.empty') }}
            </p>
        @else
            {{-- The map bundle is loaded by a Dashboard-scoped render hook
                 (MainPanelProvider): Livewire's @assets injection does not
                 execute module scripts for a lazily-loaded widget. --}}
            {{-- wire:ignore: jsVectorMap owns this subtree; a Livewire morph
                 would wipe the map. --}}
            {{-- Sized by the widget's own CSS (see clicks-geo-map.css) — the
                 panel's precompiled styles carry no app Tailwind utilities. --}}
            <div
                wire:ignore
                data-clicks-geo-map
                data-markers="{{ json_encode($markers) }}"
                data-wheel-hint="{{ __('widgets.clicks_geo_map.wheel_zoom_hint') }}"
                data-touch-hint="{{ __('widgets.clicks_geo_map.touch_pan_hint') }}"
            ></div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
