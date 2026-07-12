<?php

return [
    'label' => 'Ссылка',
    'plural_label' => 'Ссылки',
    'navigation_label' => 'Ссылки',

    'no_rules_warning_title' => 'У ссылки пока нет правил',
    'no_rules_warning_body' => 'Добавьте хотя бы одно правило ниже — иначе ссылка отдаёт 404.',

    'fields' => [
        'service_id' => 'Сервис',
        'service' => 'Сервис',
        'domain_id' => 'Домен',
        'domain' => 'Домен',
        'code' => 'Код',
        'short_url' => 'Короткая ссылка',
        'short_url_copied' => 'Скопировано',
        'clicks_count' => 'Клики',
        'clicks_count_non_bots' => 'без ботов: :count',
        'valid_since' => 'Активна с',
        'valid_since_help' => 'До этого момента ссылка отвечает 404 (ни клика, ни колбека).',
        'valid_until' => 'Активна до',
        'valid_until_help' => 'После этого момента ссылка отвечает 404. Границы окна включительны.',
        'max_clicks' => 'Лимит переходов',
        'max_clicks_help' => 'Считаются все клики, включая ботов. По достижении лимита ссылка отвечает 404.',
        'title' => 'Заголовок',
        'forward_query' => 'Пробрасывать query-параметры',
        'forward_query_help' => 'Когда включено, query-параметры входящего запроса добавляются к целевому URL.',
        'forward_query_yes' => 'Query-параметры пробрасываются',
        'forward_query_no' => 'Query-параметры не пробрасываются',
        'callback_data' => 'Данные колбека',
        'callback_data_help' => 'JSON-объект payload’а колбека (можно вложенный). В значениях-строках работают плейсхолдеры: {{click.*}} и {{link.*}}. Оставь пустым, чтобы не отправлять колбек для этой ссылки.',
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
            'conditions' => 'Условия (должны совпасть все)',
            'variants' => 'A/B-варианты',
            'variants_help' => 'Распределить трафик правила по взвешенным целям. Пусто — одна цель; 2+ — сплит.',
            'variants_min' => 'Добавьте минимум 2 варианта или ни одного.',
            'variant_url' => 'Целевой URL',
            'variant_weight' => 'Вес',
            'variant_label' => 'Метка',
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
        'threshold_warning' => 'Внимание: у ссылки уже :count кликов — удаление выведет её из ротации.',
    ],

    'filters' => [
        'only_alive' => 'Только живые',
    ],

    'pages' => [
        'create_title' => 'Создать ссылку',
        'edit_title' => 'Редактирование ссылки',
        'view_title' => 'Просмотр ссылки',
    ],
];
