{{-- Leaflet + the geo map bootstrap for the dashboard's ClicksGeoMap widget.
     Rendered via a Dashboard-scoped render hook: the widget itself is lazy, so
     its own @assets would arrive in a Livewire update that never executes
     module scripts. --}}
@vite('resources/js/widgets/clicks-geo-map.js')
