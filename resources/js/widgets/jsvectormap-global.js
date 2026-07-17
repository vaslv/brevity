import jsVectorMap from 'jsvectormap';

// dist/maps/world.js is a classic script, not a module: it registers the map
// data by calling `jsVectorMap.addMap()` on the global, so the global must
// exist before that file is evaluated. The entry imports this module first.
window.jsVectorMap = jsVectorMap;

export default jsVectorMap;
