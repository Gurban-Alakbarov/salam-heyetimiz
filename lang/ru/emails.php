<?php

// A8 — transactional email copy per language (TemplatedMailer renders in the recipient's language).
return [
    'support' => 'Поддержка',
    'automatic' => 'Это автоматическое сообщение, не отвечайте на него.',

    'subjects' => [
        'application_rejected' => 'Salam Sesame — ваша заявка не одобрена',
        'payment_receipt' => 'Salam Sesame — квитанция об оплате',
        'refund_receipt' => 'Salam Sesame — квитанция о возврате',
    ],

    'application_rejected' => [
        'greeting' => 'Здравствуйте, :name!',
        'legal_body' => 'К сожалению, ваша заявка для «:complex» не одобрена.',
        'individual_body' => 'К сожалению, ваша заявка на установку системы доступа по вашему адресу (:address) не одобрена.',
        'reason' => 'Причина: :reason',
        'retry' => 'Вы можете исправить данные и подать заявку снова в приложении.',
    ],

    'receipt' => [
        'paid_title' => 'Оплата получена',
        'refund_title' => 'Средства возвращены',
        'seller' => 'Продавец',
        'order' => 'Заказ',
        'date' => 'Дата',
        'service' => 'Подписка',
        'service_value' => ':door, до :date',
        'amount' => 'Сумма',
        'refund_amount' => 'Сумма возврата',
        'card' => 'Карта',
        'test_notice' => 'Это тестовый платёж: реальные деньги не списаны.',
    ],
];
