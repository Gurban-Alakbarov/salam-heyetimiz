<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BirPay refund + payment enrichment (DATABASE_CHANGE_PLAN M2/M3/M7). All additive + nullable.
 * - refunds.bank_refund_id   — BirPay refund id (originalId points back to the payment).
 * - refunds.completed_at     — when the refund settled.
 * - payments.approval_code   — authorization code from authorizationDetail.approvalCode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->string('bank_refund_id', 60)->nullable()->after('linked_payment_id');
            $table->timestamp('completed_at')->nullable()->after('error_message');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->string('approval_code', 20)->nullable()->after('card_last4');
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropColumn(['bank_refund_id', 'completed_at']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('approval_code');
        });
    }
};
