<?php

// A8 — transactional email copy per language (TemplatedMailer renders in the recipient's language).
return [
    'support' => 'Dəstək',
    'automatic' => 'Bu avtomatik mesajdır, cavab verməyin.',

    'subjects' => [
        'application_rejected' => 'Salam Həyətimiz — müraciətiniz təsdiqlənmədi',
        'payment_receipt' => 'Salam Sesame — ödəniş qəbzi',
        'refund_receipt' => 'Salam Sesame — geri qaytarma qəbzi',
    ],

    'application_rejected' => [
        'greeting' => 'Salam, :name!',
        'legal_body' => '":complex" üçün göndərdiyiniz müraciət təəssüf ki, təsdiqlənmədi.',
        'individual_body' => 'Ünvanınızda (:address) giriş sisteminin quraşdırılması üçün göndərdiyiniz müraciət təəssüf ki, təsdiqlənmədi.',
        'reason' => 'Səbəb: :reason',
        'retry' => 'Məlumatları düzəldib tətbiqdən yenidən müraciət edə bilərsiniz.',
    ],

    'receipt' => [
        'paid_title' => 'Ödəniş qəbul edildi',
        'refund_title' => 'Vəsait geri qaytarıldı',
        'seller' => 'Satıcı',
        'order' => 'Sifariş',
        'date' => 'Tarix',
        'service' => 'Abunəlik',
        'service_value' => ':door, :date tarixinədək',
        'amount' => 'Məbləğ',
        'refund_amount' => 'Qaytarılan məbləğ',
        'card' => 'Kart',
        'test_notice' => 'Test ödənişidir: kartdan real pul tutulmayıb.',
    ],
];
