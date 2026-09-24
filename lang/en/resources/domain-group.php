<?php

return [
    'label' => 'Domain group',
    'plural_label' => 'Domain groups',
    'navigation_label' => 'Domain groups',

    'fields' => [
        'name' => 'Name',
        'code' => 'Code',
        'code_hint' => 'Machine name used by the API (filter domains by group). Letters, digits, dashes/underscores.',
        'domains' => 'Domains',
        'domains_hint' => 'The same domain can belong to several groups.',
        'domains_count' => 'Domains',
        'domains_empty' => 'No domains',
        'created_at' => 'Created at',
    ],

    'pages' => [
        'create_title' => 'Create domain group',
        'edit_title' => 'Edit domain group',
        'view_title' => 'View domain group',
    ],

    'actions' => [
        'detach_domain' => [
            'label' => 'Detach domain',
            'hint' => 'The domain will be removed from this group only. The domain and its other memberships will be preserved.',
        ],
    ],
];
