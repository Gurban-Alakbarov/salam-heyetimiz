<?php

// A8 — transactional email copy per language (TemplatedMailer renders in the recipient's language).
return [
    'support' => 'Support',
    'automatic' => 'This is an automatic message, please do not reply.',

    'subjects' => [
        'application_rejected' => 'Salam Sesame — your application was not approved',
        'payment_receipt' => 'Salam Sesame — payment receipt',
        'refund_receipt' => 'Salam Sesame — refund receipt',
    ],

    'application_rejected' => [
        'greeting' => 'Hello, :name!',
        'legal_body' => 'Unfortunately, your application for ":complex" was not approved.',
        'individual_body' => 'Unfortunately, your application to install an access system at your address (:address) was not approved.',
        'reason' => 'Reason: :reason',
        'retry' => 'You can correct the details and apply again from the app.',
    ],

    'receipt' => [
        'paid_title' => 'Payment received',
        'refund_title' => 'Refund issued',
        'seller' => 'Seller',
        'order' => 'Order',
        'date' => 'Date',
        'service' => 'Subscription',
        'service_value' => ':door, until :date',
        'amount' => 'Amount',
        'refund_amount' => 'Refunded amount',
        'card' => 'Card',
        'test_notice' => 'This is a test payment: no real money was charged.',
    ],
];
