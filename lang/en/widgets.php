<?php

return [
    'stats' => [
        'links' => 'Links',
        'clicks' => 'Clicks',
        'callbacks' => 'Callbacks',
        'vs_previous_period' => ':change vs previous period (:previous)',
        'previous_period_empty' => 'No data for the previous period',
    ],
    'clicks_chart' => [
        'heading' => 'Clicks over the last :days days',
        'filter' => ':days days',
        'dataset' => 'Clicks',
    ],
    'links_per_domain_chart' => [
        'heading' => 'Links per domain',
        'dataset' => 'Links',
    ],
    'clicks_geo_map' => [
        'heading' => 'Click geography (last :days days)',
        'empty' => 'No located clicks yet.',
        'wheel_zoom_hint' => 'Use :key + scroll to zoom the map',
        'touch_pan_hint' => 'Use two fingers to move the map',
    ],
];
