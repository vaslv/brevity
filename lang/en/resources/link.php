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
        'clicks_count_non_bots' => 'non-bots: :count',
        'valid_since' => 'Valid since',
        'valid_since_help' => 'Before this moment the link responds 404 (no click, no callback).',
        'valid_until' => 'Valid until',
        'valid_until_help' => 'After this moment the link responds 404. Window edges are inclusive.',
        'max_clicks' => 'Click limit',
        'max_clicks_help' => 'All clicks count, bots included. On reaching the limit the link responds 404.',
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
            'conditions' => 'Conditions (all must match)',
            'variants' => 'A/B variants',
            'variants_help' => 'Split winning traffic across weighted targets. Leave empty for a single destination; add 2+ for a split.',
            'variants_min' => 'Add at least 2 variants, or none.',
            'variant_url' => 'Target URL',
            'variant_weight' => 'Weight',
            'variant_label' => 'Label',
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
        'threshold_warning' => 'Warning: this link already has :count clicks — deleting removes it from rotation.',
    ],

    'filters' => [
        'only_alive' => 'Alive only',
    ],

    'pages' => [
        'create_title' => 'Create link',
        'edit_title' => 'Edit link',
        'view_title' => 'View link',
    ],
];
