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
    ],

    'data_fields' => [
        'time_before' => [
            'before' => 'Срабатывать, пока текущее время меньше',
        ],
    ],
];
