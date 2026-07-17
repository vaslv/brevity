import jsVectorMap from './jsvectormap-global';
import 'jsvectormap/dist/maps/world.js';
import 'jsvectormap/dist/jsvectormap.css';
import '../../css/widgets/clicks-geo-map.css';

// teal-600, matching the panel's primary color.
const BUBBLE_COLOR = '#0d9488';

function initClicksGeoMaps() {
    document.querySelectorAll('[data-clicks-geo-map]').forEach((el) => {
        if (el.dataset.mapInitialized) {
            return;
        }

        el.dataset.mapInitialized = 'true';

        const markers = JSON.parse(el.dataset.markers || '[]');

        if (markers.length === 0) {
            return;
        }

        const maxCount = Math.max(...markers.map((marker) => marker.count));

        new jsVectorMap({
            selector: el,
            map: 'world',
            backgroundColor: 'transparent',
            // Scrolling the dashboard past the map must not zoom it; zooming
            // stays available through the buttons.
            zoomOnScroll: false,
            markers: markers.map((marker) => ({
                // The default tooltip prints the marker name as-is.
                name: `${marker.label} — ${marker.count}`,
                coords: [marker.lat, marker.lng],
                // sqrt scale: bubble area (not radius) tracks the click
                // count, so a city with 4x the clicks reads as 4x the bubble,
                // capped at 4..12px.
                style: {
                    initial: { r: 4 + 8 * Math.sqrt(marker.count / maxCount) },
                },
            })),
            markerStyle: {
                initial: {
                    fill: BUBBLE_COLOR,
                    fillOpacity: 0.7,
                    stroke: '#fff',
                    strokeWidth: 1.5,
                    strokeOpacity: 0.6,
                },
                hover: {
                    fill: BUBBLE_COLOR,
                    fillOpacity: 1,
                    cursor: 'default',
                },
            },
        });
    });
}

initClicksGeoMaps();

// The widget is lazy: its HTML arrives in a Livewire update after this module
// has already run, so watch the DOM for the container instead of relying on
// load-time events.
new MutationObserver(initClicksGeoMaps).observe(document.body, {
    childList: true,
    subtree: true,
});
