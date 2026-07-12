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
    ],

    'describe' => [
        'time_before' => 'Before :datetime',
        'after_date' => 'After :datetime',
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
    ],

    'pages' => [
        'create_title' => 'Create condition',
        'edit_title' => 'Edit condition',
        'view_title' => 'View condition',
    ],
];
