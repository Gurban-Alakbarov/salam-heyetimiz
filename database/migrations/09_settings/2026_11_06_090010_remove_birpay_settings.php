<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// BirPay is gone: the Kapital e-commerce API needs only a login and password. Its credentials must not stay behind.
return new class extends Migration
{
    private const KEYS = [
        'payments.cancel_url',
        'payments.enable_webhook_logging',
        'payments.fail_url',
        'payments.kapital_api_base_url',
        'payments.kapital_checkout_url',
        'payments.kapital_client_id',
        'payments.kapital_client_secret',
        'payments.kapital_ip_allowlist',
        'payments.kapital_merchant_id',
        'payments.kapital_merchant_name',
        'payments.kapital_scope',
        'payments.kapital_terminal_id',
        'payments.kapital_webhook_secret',
        'payments.success_url',
    ];

    public function up(): void
    {
        DB::table('settings')->whereIn('key', self::KEYS)->delete();
    }

    public function down(): void
    {
        // The deleted values were retired credentials; there is nothing to restore.
    }
};
