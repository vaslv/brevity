{{-- Leaflet + the geo map bootstrap for the dashboard's ClicksGeoMap widget.
     Rendered via a Dashboard-scoped render hook: the widget itself is lazy, so
     its own @assets would arrive in a Livewire update that never executes
     module scripts. --}}
{{-- Fail-safe: a deploy without built assets must cost the dashboard its map,
     not take the whole page down with a ViteManifestNotFoundException. --}}
@php($vite = app(\Illuminate\Foundation\Vite::class))
@if ($vite->isRunningHot() || is_file(public_path('build/manifest.json')))
    @vite('resources/js/widgets/clicks-geo-map.js')
@endif
