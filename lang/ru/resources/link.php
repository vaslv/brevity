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
        'forward_query_help' => 'Когда включено, query-параметры входящего запроса добавляются к целевому URL.',
        'callback_data' => 'Данные колбека',
        'callback_data_help' => 'Пары ключ-значение, которые уходят в payload колбека. В значениях-строках работают плейсхолдеры: {{click.*}} и {{link.*}}. Оставь пустым, чтобы не отправлять колбек для этой ссылки.',
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
            'transition_mode_help' => 'Как сервер отвечает при срабатывании правила. По умолчанию — Прямой (302 редирект).',
            'priority' => 'Приоритет',
            'priority_help' => 'Меньше число — раньше проверяется. Первое подошедшее правило побеждает.',
            'created_at' => 'Создано',
        ],
    ],

    'transition_modes' => [
        'direct' => 'Прямой',
        'manual' => 'Ручной',
        'delayed' => 'Отложенный',
    ],
];
