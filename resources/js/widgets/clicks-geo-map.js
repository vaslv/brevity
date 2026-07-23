import jsVectorMap from './jsvectormap-global';
import 'jsvectormap/dist/maps/world.js';
import 'jsvectormap/dist/jsvectormap.css';
import '../../css/widgets/clicks-geo-map.css';

// teal-600, matching the panel's primary color.
const BUBBLE_COLOR = '#0d9488';

// Zoom per pixel of wheel travel. The library's own formula throws the delta
// away (its `>> 10` collapses to a fixed step), so a trackpad pinch — which
// fires a stream of tiny deltas — applied a full mouse notch each time and the
// zoom jumped. Scaling by the actual delta keeps the pinch smooth; the clamp
// keeps one coarse mouse notch (~100px, or a whole "page" on some drivers) to
// about 13% instead of a leap.
const ZOOM_SENSITIVITY = 0.002;
const MAX_WHEEL_DELTA = 60;

// deltaMode 1 counts lines, 2 counts pages — normalize both to pixels.
const WHEEL_LINE_HEIGHT = 16;

// Google Maps-like gestures. Mouse: a plain wheel keeps scrolling the page
// (the map is dashboard-wide — grabbing the wheel would trap the scroll) and
// briefly shows a hint; Ctrl/⌘ + wheel zooms proportionally to the wheel
// delta, anchored at the cursor. Touch: single-finger events
// are hidden from the library in the capture phase so a finger scrolls the
// page instead of panning the map (the library would preventDefault it), and
// two fingers pinch-zoom (library) + pan (added here — the library's
// two-touch handler only scales).
function attachMapGestures(el, map) {
    const isApple = /Mac|iPhone|iPad/i.test(navigator.userAgent);
    const wheelHint = (el.dataset.wheelHint || '').replace(':key', isApple ? '⌘' : 'Ctrl');
    const touchHint = el.dataset.touchHint || '';

    const hint = document.createElement('div');
    hint.className = 'clicks-geo-map-hint';
    el.appendChild(hint);

    let hideTimer;

    const showHint = (text) => {
        hint.textContent = text;
        hint.classList.add('is-visible');
        clearTimeout(hideTimer);
        hideTimer = setTimeout(() => hint.classList.remove('is-visible'), 1500);
    };

    el.addEventListener(
        'wheel',
        (event) => {
            if (!event.ctrlKey && !event.metaKey) {
                showHint(wheelHint);

                return;
            }

            event.preventDefault();
            hint.classList.remove('is-visible');

            // Zooming is driven here rather than by the library (zoomOnScroll
            // is disabled on the instance, so it cannot double-fire).
            let delta = event.deltaY;

            if (event.deltaMode === 1) {
                delta *= WHEEL_LINE_HEIGHT;
            } else if (event.deltaMode === 2) {
                delta *= el.clientHeight;
            }

            delta = Math.max(-MAX_WHEEL_DELTA, Math.min(MAX_WHEEL_DELTA, delta));

            const factor = Math.exp(-delta * ZOOM_SENSITIVITY);
            const rect = el.getBoundingClientRect();

            map._tooltip?.hide();
            map._setScale(
                map.scale * factor,
                event.pageX - rect.left - window.scrollX,
                event.pageY - rect.top - window.scrollY,
            );
        },
        { passive: false },
    );

    let touchCenter = null;

    const interceptTouch = (event) => {
        if (event.type === 'touchend') {
            touchCenter = null;

            return;
        }

        if (event.touches.length === 1) {
            event.stopPropagation();
            touchCenter = null;

            if (event.type === 'touchmove') {
                showHint(touchHint);
            }

            return;
        }

        hint.classList.remove('is-visible');

        if (event.type !== 'touchmove' || event.touches.length !== 2) {
            touchCenter = null;

            return;
        }

        const centerX = (event.touches[0].pageX + event.touches[1].pageX) / 2;
        const centerY = (event.touches[0].pageY + event.touches[1].pageY) / 2;

        if (touchCenter) {
            map.transX -= (touchCenter.x - centerX) / map.scale;
            map.transY -= (touchCenter.y - centerY) / map.scale;
            map._applyTransform();
        }

        touchCenter = { x: centerX, y: centerY };
    };

    // Capture phase on the parent runs before the library's own container
    // listeners — stopPropagation() is what hides single-finger events from it.
    const parent = el.parentElement;
    parent.addEventListener('touchstart', interceptTouch, { capture: true, passive: true });
    parent.addEventListener('touchmove', interceptTouch, { capture: true, passive: true });
    parent.addEventListener('touchend', interceptTouch, { capture: true, passive: true });
}

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

        const map = new jsVectorMap({
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

        attachMapGestures(el, map);
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
