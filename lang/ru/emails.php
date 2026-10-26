<?php

// A8 — transactional email copy per language (TemplatedMailer renders in the recipient's language).
return [
    'support' => 'Поддержка',
    'automatic' => 'Это автоматическое сообщение, не отвечайте на него.',

    'subjects' => [
        'application_rejected' => 'Salam Həyətimiz — ваша заявка не одобрена',
    ],

    'application_rejected' => [
        'greeting' => 'Здравствуйте, :name!',
        'legal_body' => 'К сожалению, ваша заявка для «:complex» не одобрена.',
        'individual_body' => 'К сожалению, ваша заявка на установку системы доступа по вашему адресу (:address) не одобрена.',
        'reason' => 'Причина: :reason',
        'retry' => 'Вы можете исправить данные и подать заявку снова в приложении.',
    ],
];
