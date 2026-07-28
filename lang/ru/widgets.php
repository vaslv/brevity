<?php

return [
    'stats' => [
        'links' => 'Ссылки',
        'clicks' => 'Клики',
        'callbacks' => 'Колбеки',
        'vs_previous_period' => ':change к прошлому периоду (:previous)',
        'previous_period_empty' => 'Нет данных за прошлый период',
    ],
    'clicks_chart' => [
        'heading' => 'Клики за последние :days дней',
        'filter' => ':days дней',
        'dataset' => 'Клики',
    ],
    'links_per_domain_chart' => [
        'heading' => 'Ссылки по доменам',
        'dataset' => 'Ссылки',
    ],
    'clicks_geo_map' => [
        'heading' => 'География кликов (:days дней)',
        'empty' => 'Кликов с геоданными пока нет.',
        'wheel_zoom_hint' => 'Чтобы изменить масштаб, используйте :key + прокрутку',
        'touch_pan_hint' => 'Двигайте карту двумя пальцами',
    ],
];
