<?php

return [
    'label' => 'Service',
    'plural_label' => 'Services',
    'navigation_label' => 'Services',

    'fields' => [
        'name' => 'Name',
        'callback_url' => 'Callback URL',
        'callback_url_help' => 'POST endpoint to notify after each click. Leave empty to disable callbacks for this service.',
        'created_at' => 'Created at',
    ],

    'tokens' => [
        'title' => 'API tokens',
        'fields' => [
            'name' => 'Name',
            'abilities' => 'Abilities',
            'last_used_at' => 'Last used at',
            'created_at' => 'Created at',
        ],
        'actions' => [
            'create' => 'Create API token',
            'modal_heading' => 'Create a new API token?',
            'modal_description' => 'The token will be shown once. Store it securely.',
            'modal_submit' => 'Create',
        ],
        'notifications' => [
            'created_title' => 'API token created',
            'token_line' => 'Token: :token',
            'hint' => 'Hint: copy and store the token — it will not be shown again.',
        ],
    ],
];
