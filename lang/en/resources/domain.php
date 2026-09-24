<?php

return [
    'label' => 'Domain',
    'plural_label' => 'Domains',
    'navigation_label' => 'Domains',

    'fields' => [
        'value' => 'Value',
        'is_default' => 'Default',
        'is_default_hint' => 'Used when a link is created without an explicit domain.',
        'created_at' => 'Created at',
    ],

    'pages' => [
        'create_title' => 'Create domain',
        'edit_title' => 'Edit domain',
        'view_title' => 'View domain',
    ],

    'actions' => [
        'attach_to_group' => [
            'label' => 'Attach to group',
            'group' => 'Group',
            'notification' => 'Domains attached to group.',
        ],
        'set_as_default' => [
            'label' => 'Set as default',
            'modal_heading' => 'Set default domain',
            'modal_description' => 'This domain will be used for new links created without an explicit domain. The current default will be unset.',
            'modal_submit' => 'Set as default',
            'notification' => 'Domain :domain is now the default.',
        ],
    ],

    'groups' => [
        'title' => 'Groups',
        'empty' => 'This domain does not belong to any groups',
        'detach' => 'Detach group',
        'detach_hint' => 'Only this membership will be removed. The domain, group and other memberships will be preserved.',
    ],
];
