import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import '../../css/widgets/clicks-geo-map.css';

const TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const ATTRIBUTION =
    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

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

        // scrollWheelZoom off so scrolling the dashboard past the map does not
        // zoom it; zooming stays available via the controls and double-click.
        const map = L.map(el, { scrollWheelZoom: false });

        L.tileLayer(TILE_URL, { attribution: ATTRIBUTION, maxZoom: 19 }).addTo(map);

        const maxCount = Math.max(...markers.map((marker) => marker.count));

        markers.forEach((marker) => {
            // sqrt scale: area (not radius) tracks the click count, so a city
            // with 4x the clicks reads as 4x the bubble, capped at 6..20px.
            const radius = 6 + 14 * Math.sqrt(marker.count / maxCount);

            L.circleMarker([marker.lat, marker.lng], {
                radius,
                stroke: false,
                fillColor: BUBBLE_COLOR,
                fillOpacity: 0.55,
            })
                .bindTooltip(`${marker.label} — ${marker.count}`)
                .addTo(map);
        });

        // Fit whatever geography the data has (one city, one country, the
        // world); maxZoom keeps a single dominant city from filling the screen.
        map.fitBounds(
            markers.map((marker) => [marker.lat, marker.lng]),
            { padding: [30, 30], maxZoom: 10 },
        );
    });
}

initClicksGeoMaps();
document.addEventListener('livewire:navigated', initClicksGeoMaps);
