<?php

return [
    'label' => 'Пользователь',
    'plural_label' => 'Пользователи',
    'navigation_label' => 'Пользователи',

    'fields' => [
        'name' => 'Имя',
        'email' => 'Email',
        'email_verified_at' => 'Email подтверждён',
        'verified' => 'Подтверждён',
        'password' => 'Пароль',
        'password_confirmation' => 'Подтверждение пароля',
        'created_at' => 'Создан',
        'updated_at' => 'Обновлён',
    ],

    'helpers' => [
        'email_verified_at' => 'Оставьте пустым, чтобы email считался неподтверждённым.',
        'password_on_edit' => 'Оставьте пустым, чтобы не менять текущий пароль.',
    ],

    'placeholders' => [
        'not_verified' => 'Не подтверждён',
    ],
];
