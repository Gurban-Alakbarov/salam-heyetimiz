<?php

// Baseline validation messages (R-LOC; Roadmap Phase 1A). `phone` is the custom
// E.164 AZ rule. Extend as rules are introduced.
return [
    'required' => ':attribute sahəsi tələb olunur.',
    'email' => ':attribute düzgün e-poçt ünvanı olmalıdır.',
    'confirmed' => ':attribute təsdiqi uyğun gəlmir.',
    'unique' => ':attribute artıq istifadə olunub.',
    'regex' => ':attribute formatı yanlışdır.',
    'string' => ':attribute mətn olmalıdır.',
    'integer' => ':attribute tam ədəd olmalıdır.',
    'numeric' => ':attribute ədəd olmalıdır.',
    'boolean' => ':attribute yalnız doğru və ya yanlış ola bilər.',
    'date' => ':attribute düzgün tarix olmalıdır.',
    'in' => 'Seçilmiş :attribute yanlışdır.',
    'exists' => 'Seçilmiş :attribute mövcud deyil.',
    'phone' => ':attribute düzgün nömrə olmalıdır.',
    'min' => [
        'numeric' => ':attribute ən azı :min olmalıdır.',
        'string' => ':attribute ən azı :min simvol olmalıdır.',
        'array' => ':attribute ən azı :min element olmalıdır.',
    ],
    'max' => [
        'numeric' => ':attribute :max-dan böyük olmamalıdır.',
        'string' => ':attribute :max simvoldan çox olmamalıdır.',
        'array' => ':attribute :max elementdən çox olmamalıdır.',
    ],

    'custom' => [],

    'attributes' => [
        'phone' => 'telefon nömrəsi',
        'full_name' => 'ad',
        'email' => 'e-poçt',
        'preferred_language' => 'dil',
    ],
];
