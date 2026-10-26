<?php

// A8 — transactional email copy per language (TemplatedMailer renders in the recipient's language).
return [
    'support' => 'Dəstək',
    'automatic' => 'Bu avtomatik mesajdır, cavab verməyin.',

    'subjects' => [
        'application_rejected' => 'Salam Həyətimiz — müraciətiniz təsdiqlənmədi',
    ],

    'application_rejected' => [
        'greeting' => 'Salam, :name!',
        'legal_body' => '":complex" üçün göndərdiyiniz müraciət təəssüf ki, təsdiqlənmədi.',
        'individual_body' => 'Ünvanınızda (:address) giriş sisteminin quraşdırılması üçün göndərdiyiniz müraciət təəssüf ki, təsdiqlənmədi.',
        'reason' => 'Səbəb: :reason',
        'retry' => 'Məlumatları düzəldib tətbiqdən yenidən müraciət edə bilərsiniz.',
    ],
];
