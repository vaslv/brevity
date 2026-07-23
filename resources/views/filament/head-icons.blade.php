{{-- Filament's own favicon() renders a single <link rel="icon">, so the ICO
     fallback (browsers without SVG icon support) and the iOS home-screen icon
     are declared here. --}}
<link rel="alternate icon" href="{{ asset('favicon.ico') }}" sizes="16x16 32x32 48x48 64x64">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
