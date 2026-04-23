<?php

return [
    'label' => 'Ссылка',
    'plural_label' => 'Ссылки',
    'navigation_label' => 'Ссылки',

    'fields' => [
        'service_id' => 'Сервис',
        'service' => 'Сервис',
        'domain_id' => 'Домен',
        'domain' => 'Домен',
        'code' => 'Код',
        'title' => 'Заголовок',
        'forward_query' => 'Пробрасывать query-параметры',
        'callback_data' => 'Данные колбека',
        'callback_data_key' => 'Ключ',
        'callback_data_value' => 'Значение',
        'callback_data_add' => 'Добавить',
        'created_at' => 'Создана',
        'deleted_at' => 'Удалена',
    ],

    'rules' => [
        'title' => 'Правила',
        'fields' => [
            'url_id' => 'URL',
            'url' => 'URL',
            'condition_id' => 'Условие',
            'condition' => 'Условие',
            'transition_mode' => 'Режим перехода',
            'priority' => 'Приоритет',
            'created_at' => 'Создано',
        ],
    ],

    'transition_modes' => [
        'direct' => 'Прямой',
        'manual' => 'Ручной',
        'delayed' => 'Отложенный',
    ],
];
