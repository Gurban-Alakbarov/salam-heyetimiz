<?php

// A8 — transactional email copy per language (TemplatedMailer renders in the recipient's language).
return [
    'support' => 'Support',
    'automatic' => 'This is an automatic message, please do not reply.',

    'subjects' => [
        'application_rejected' => 'Salam Həyətimiz — your application was not approved',
    ],

    'application_rejected' => [
        'greeting' => 'Hello, :name!',
        'legal_body' => 'Unfortunately, your application for ":complex" was not approved.',
        'individual_body' => 'Unfortunately, your application to install an access system at your address (:address) was not approved.',
        'reason' => 'Reason: :reason',
        'retry' => 'You can correct the details and apply again from the app.',
    ],
];
