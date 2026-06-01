<?php

return [
    'label' => 'Link',
    'plural_label' => 'Links',
    'navigation_label' => 'Links',

    'no_rules_warning_title' => 'This link has no rules yet',
    'no_rules_warning_body' => 'Add at least one rule below — until then the link resolves to a 404.',

    'fields' => [
        'service_id' => 'Service',
        'service' => 'Service',
        'domain_id' => 'Domain',
        'domain' => 'Domain',
        'code' => 'Code',
        'short_url' => 'Short URL',
        'short_url_copied' => 'Copied',
        'clicks_count' => 'Clicks',
        'title' => 'Title',
        'forward_query' => 'Forward query',
        'forward_query_help' => 'When enabled, query parameters of the incoming request are appended to the target URL.',
        'forward_query_yes' => 'Query parameters are forwarded',
        'forward_query_no' => 'Query parameters are not forwarded',
        'callback_data' => 'Callback data',
        'callback_data_help' => 'JSON object sent as the callback payload (nesting allowed). String values support placeholders: {{click.*}} and {{link.*}}. Leave empty to skip the callback for this link.',
        'callback_data_key' => 'Key',
        'callback_data_value' => 'Value',
        'callback_data_add' => 'Add',
        'created_at' => 'Created at',
        'deleted_at' => 'Deleted at',
    ],

    'rules' => [
        'title' => 'Rules',
        'actions' => [
            'create_label' => 'Create rule',
            'create_heading' => 'Create rule',
            'edit_heading' => 'Edit rule',
            'delete_heading' => 'Delete rule?',
        ],
        'fields' => [
            'url_id' => 'URL',
            'url' => 'URL',
            'condition_id' => 'Condition',
            'condition' => 'Condition',
            'transition_mode' => 'Transition mode',
            'transition_mode_help' => 'How the server responds when this rule matches. Default is Direct (302 redirect).',
            'priority' => 'Priority',
            'priority_help' => 'Lower numbers run first. The first matching rule wins.',
            'created_at' => 'Created at',
        ],
    ],

    'transition_modes' => [
        'direct' => 'Direct',
        'manual' => 'Manual',
        'delayed' => 'Delayed',
    ],

    'delete' => [
        'modal_heading' => 'Delete link :code?',
        'modal_description' => 'Link :code will be soft-deleted. Its clicks and callbacks remain in history. You can restore it afterwards.',
    ],

    'force_delete' => [
        'modal_heading' => 'Permanently delete link :code?',
        'modal_description' => 'Link :code will be removed for good. This cannot be undone.',
    ],

    'pages' => [
        'create_title' => 'Create link',
        'edit_title' => 'Edit link',
        'view_title' => 'View link',
    ],
];
