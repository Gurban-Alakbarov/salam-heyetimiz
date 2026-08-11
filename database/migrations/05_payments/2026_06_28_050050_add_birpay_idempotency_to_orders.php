<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BirPay (Kapital Checkout V1.3) integration — M1 (DATABASE_CHANGE_PLAN.md, REQUIRED for Phase 2).
 * A stable per-order `X-Idempotency-Key` reused across create-payment retries (safe 5xx replay). Additive +
 * nullable + unique → zero-downtime.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('bank_idempotency_key', 40)->nullable()->unique()->after('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['bank_idempotency_key']);
            $table->dropColumn('bank_idempotency_key');
        });
    }
};
