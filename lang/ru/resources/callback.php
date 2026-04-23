<?php

return [
    'label' => 'Колбек',
    'plural_label' => 'Колбеки',
    'navigation_label' => 'Колбеки',

    'fields' => [
        'service' => 'Сервис',
        'click_id' => 'ID клика',
        'response_code' => 'Код ответа',
        'response_body' => 'Тело ответа',
        'status' => 'Статус',
        'attempts' => 'Попытки',
        'last_attempt_at' => 'Последняя попытка',
        'created_at' => 'Создан',
    ],

    'statuses' => [
        'pending' => 'Ожидание',
        'sent' => 'Отправлен',
        'failed' => 'Ошибка',
    ],
];
