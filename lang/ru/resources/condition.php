<?php

return [
    'label' => 'Условие',
    'plural_label' => 'Условия',
    'navigation_label' => 'Условия',

    'fields' => [
        'type' => 'Тип',
        'data' => 'Данные',
        'created_at' => 'Создано',
    ],

    'helpers' => [
        'type_immutable' => 'Тип нельзя изменить после создания.',
    ],

    'types' => [
        'time_before' => 'До указанного времени',
        'after_date' => 'После указанной даты',
        'query_param' => 'Query-параметр',
    ],

    'describe' => [
        'time_before' => 'До :datetime',
        'after_date' => 'После :datetime',
        'query_param' => 'Query :key=:value',
    ],

    'data_fields' => [
        'time_before' => [
            'before' => 'Срабатывать, пока текущее время меньше',
            'before_help' => 'Правило срабатывает, пока текущее время меньше указанного.',
        ],
        'after_date' => [
            'after' => 'Срабатывать, когда текущее время не раньше',
            'after_help' => 'Правило срабатывает, когда текущее время достигло указанного (включительно).',
        ],
        'query_param' => [
            'key' => 'Имя query-параметра',
            'key_help' => 'Правило срабатывает, когда в URL визита есть этот параметр с точным значением ниже.',
            'value' => 'Ожидаемое значение',
        ],
    ],

    'pages' => [
        'create_title' => 'Создать условие',
        'edit_title' => 'Редактирование условия',
        'view_title' => 'Просмотр условия',
    ],
];
