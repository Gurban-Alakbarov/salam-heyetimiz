<?php

return [
    'required' => 'The :attribute field is required.',
    'email' => 'The :attribute must be a valid email address.',
    'confirmed' => 'The :attribute confirmation does not match.',
    'unique' => 'The :attribute has already been taken.',
    'regex' => 'The :attribute format is invalid.',
    'string' => 'The :attribute must be a string.',
    'integer' => 'The :attribute must be an integer.',
    'numeric' => 'The :attribute must be a number.',
    'boolean' => 'The :attribute field must be true or false.',
    'date' => 'The :attribute must be a valid date.',
    'in' => 'The selected :attribute is invalid.',
    'exists' => 'The selected :attribute does not exist.',
    'phone' => 'The :attribute must be a valid number.',
    'min' => [
        'numeric' => 'The :attribute must be at least :min.',
        'string' => 'The :attribute must be at least :min characters.',
        'array' => 'The :attribute must have at least :min items.',
    ],
    'max' => [
        'numeric' => 'The :attribute must not be greater than :max.',
        'string' => 'The :attribute must not be greater than :max characters.',
        'array' => 'The :attribute must not have more than :max items.',
    ],

    'custom' => [],

    'attributes' => [
        'phone' => 'phone number',
        'full_name' => 'name',
        'email' => 'email',
        'preferred_language' => 'language',
    ],
];
