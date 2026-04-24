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
        'short_url' => 'Короткая ссылка',
        'short_url_copied' => 'Скопировано',
        'clicks_count' => 'Клики',
        'title' => 'Заголовок',
        'forward_query' => 'Пробрасывать query-параметры',
        'forward_query_help' => 'Когда включено, query-параметры входящего запроса добавляются к целевому URL.',
        'forward_query_yes' => 'Query-параметры пробрасываются',
        'forward_query_no' => 'Query-параметры не пробрасываются',
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
        'actions' => [
            'create_label' => 'Создать правило',
            'create_heading' => 'Создать правило',
            'edit_heading' => 'Редактирование правила',
            'delete_heading' => 'Удалить правило?',
        ],
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

    'delete' => [
        'modal_heading' => 'Удалить ссылку :code?',
        'modal_description' => 'Ссылка :code будет удалена мягко. Её клики и колбеки останутся в истории. Позже её можно восстановить.',
    ],

    'force_delete' => [
        'modal_heading' => 'Удалить ссылку :code навсегда?',
        'modal_description' => 'Ссылка :code будет удалена безвозвратно. Отменить нельзя.',
    ],

    'pages' => [
        'create_title' => 'Создать ссылку',
        'edit_title' => 'Редактирование ссылки',
        'view_title' => 'Просмотр ссылки',
    ],
];
