<?php

return [
    'required' => 'Поле :attribute обязательно.',
    'email' => ':attribute должен быть корректным e-mail.',
    'confirmed' => 'Подтверждение :attribute не совпадает.',
    'unique' => ':attribute уже занято.',
    'regex' => 'Неверный формат :attribute.',
    'string' => ':attribute должен быть строкой.',
    'integer' => ':attribute должен быть целым числом.',
    'numeric' => ':attribute должен быть числом.',
    'boolean' => ':attribute может быть только да или нет.',
    'date' => ':attribute должен быть корректной датой.',
    'in' => 'Выбранное значение :attribute некорректно.',
    'exists' => 'Выбранное значение :attribute не существует.',
    'phone' => ':attribute должен быть корректным номером.',
    'min' => [
        'numeric' => ':attribute должен быть не менее :min.',
        'string' => ':attribute должен содержать не менее :min символов.',
        'array' => ':attribute должен содержать не менее :min элементов.',
    ],
    'max' => [
        'numeric' => ':attribute не должен превышать :max.',
        'string' => ':attribute не должен превышать :max символов.',
        'array' => ':attribute не должен содержать более :max элементов.',
    ],

    'custom' => [],

    'attributes' => [
        'phone' => 'номер телефона',
        'full_name' => 'имя',
        'email' => 'e-mail',
        'preferred_language' => 'язык',
    ],
];
