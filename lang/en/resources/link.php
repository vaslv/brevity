<?php

return [
    'label' => 'Link',
    'plural_label' => 'Links',
    'navigation_label' => 'Links',

    'fields' => [
        'service_id' => 'Service',
        'service' => 'Service',
        'domain_id' => 'Domain',
        'domain' => 'Domain',
        'code' => 'Code',
        'title' => 'Title',
        'forward_query' => 'Forward query',
        'callback_data' => 'Callback data',
        'callback_data_key' => 'Key',
        'callback_data_value' => 'Value',
        'callback_data_add' => 'Add',
        'created_at' => 'Created at',
        'deleted_at' => 'Deleted at',
    ],

    'rules' => [
        'title' => 'Rules',
        'fields' => [
            'url_id' => 'URL',
            'url' => 'URL',
            'condition_id' => 'Condition',
            'condition' => 'Condition',
            'transition_mode' => 'Transition mode',
            'priority' => 'Priority',
            'created_at' => 'Created at',
        ],
    ],

    'transition_modes' => [
        'direct' => 'Direct',
        'manual' => 'Manual',
        'delayed' => 'Delayed',
    ],
];
