<?php

return [
    'label' => 'Condition',
    'plural_label' => 'Conditions',
    'navigation_label' => 'Conditions',

    'fields' => [
        'type' => 'Type',
        'data' => 'Data',
        'created_at' => 'Created at',
    ],

    'helpers' => [
        'type_immutable' => 'Type cannot be changed after creation.',
    ],

    'types' => [
        'time_before' => 'Time before',
        'after_date' => 'After date',
        'query_param' => 'Query parameter',
        'ip_address' => 'IP address',
        'device' => 'Device',
    ],

    'describe' => [
        'time_before' => 'Before :datetime',
        'after_date' => 'After :datetime',
        'query_param' => 'Query :key=:value',
        'ip_address' => 'IP :ip',
        'device' => 'Device :device',
    ],

    'device_types' => [
        'android' => 'Android',
        'ios' => 'iOS',
        'mobile' => 'Mobile',
        'windows' => 'Windows',
        'macos' => 'macOS',
        'linux' => 'Linux',
        'chromeos' => 'ChromeOS',
        'desktop' => 'Desktop',
    ],

    'data_fields' => [
        'time_before' => [
            'before' => 'Match while current time is before',
            'before_help' => 'Rule matches while the current time is earlier than this moment.',
        ],
        'after_date' => [
            'after' => 'Match once current time is at or after',
            'after_help' => 'Rule matches once the current time reaches this moment (inclusive).',
        ],
        'query_param' => [
            'key' => 'Query parameter name',
            'key_help' => 'Rule matches when the visit URL has this parameter with the exact value below.',
            'value' => 'Expected value',
        ],
        'ip_address' => [
            'ip' => 'IP address, CIDR or wildcard',
            'ip_help' => 'Exact IP (1.2.3.4), CIDR range (10.0.0.0/24) or IPv4 wildcard (11.22.*.*).',
            'invalid' => 'Enter an exact IP, a CIDR range, or an IPv4 wildcard.',
        ],
        'device' => [
            'device' => 'Device type',
            'device_help' => 'One user agent can match several types (an iPhone is both iOS and Mobile).',
        ],
    ],

    'pages' => [
        'create_title' => 'Create condition',
        'edit_title' => 'Edit condition',
        'view_title' => 'View condition',
    ],
];
