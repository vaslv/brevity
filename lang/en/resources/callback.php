<?php

return [
    'label' => 'Callback',
    'plural_label' => 'Callbacks',
    'navigation_label' => 'Callbacks',
    'navigation_badge_tooltip' => 'Callbacks in Failed status',

    'fields' => [
        'service' => 'Service',
        'click_id' => 'Click ID',
        'response_code' => 'Response code',
        'response_body' => 'Response body',
        'status' => 'Status',
        'attempts' => 'Attempts',
        'last_attempt_at' => 'Last attempt at',
        'created_at' => 'Created at',
    ],

    'statuses' => [
        'pending' => 'Pending',
        'sent' => 'Sent',
        'failed' => 'Failed',
    ],

    'pages' => [
        'view_title' => 'View callback',
    ],
];
