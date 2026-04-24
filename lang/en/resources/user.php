<?php

return [
    'label' => 'User',
    'plural_label' => 'Users',
    'navigation_label' => 'Users',

    'fields' => [
        'name' => 'Name',
        'email' => 'Email',
        'email_verified_at' => 'Email verified at',
        'verified' => 'Verified',
        'password' => 'Password',
        'password_confirmation' => 'Confirm password',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
    ],

    'helpers' => [
        'email_verified_at' => 'Leave blank to mark email as unverified.',
        'password_on_edit' => 'Leave blank to keep the current password.',
    ],

    'placeholders' => [
        'not_verified' => 'Not verified',
    ],

    'pages' => [
        'create_title' => 'Create user',
        'edit_title' => 'Edit user',
        'view_title' => 'View user',
    ],
];
