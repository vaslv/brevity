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
    ],

    'describe' => [
        'time_before' => 'Before :datetime',
        'after_date' => 'After :datetime',
        'query_param' => 'Query :key=:value',
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
    ],

    'pages' => [
        'create_title' => 'Create condition',
        'edit_title' => 'Edit condition',
        'view_title' => 'View condition',
    ],
];
