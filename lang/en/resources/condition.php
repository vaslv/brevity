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
    ],

    'describe' => [
        'time_before' => 'Before :datetime',
    ],

    'data_fields' => [
        'time_before' => [
            'before' => 'Match while current time is before',
            'before_help' => 'Rule matches while the current time is earlier than this moment.',
        ],
    ],

    'pages' => [
        'create_title' => 'Create condition',
        'edit_title' => 'Edit condition',
        'view_title' => 'View condition',
    ],
];
