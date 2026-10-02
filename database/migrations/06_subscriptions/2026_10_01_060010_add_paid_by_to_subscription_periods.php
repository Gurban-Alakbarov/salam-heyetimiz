<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * IMPLEMENTATION_PLAN M8 (B2). Makes the PAYER explicit on the paid-period ledger. The BENEFICIARY stays
 * subscriptions.device_user_id → device_users.user_id; the payer may be a different user (a family head
 * paying for a member — BR-3). Nullable: refund periods and comp grants have no payer.
 *
 * Backfill: idempotent — only rows still NULL, only paid (non-refund) periods, copied from
 * orders.payer_user_id. Re-running it changes nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_periods', function (Blueprint $table) {
            $table->unsignedBigInteger('paid_by_user_id')->nullable()->after('order_id');
            $table->index('paid_by_user_id', 'idx_subscription_periods_paid_by');
            $table->foreign('paid_by_user_id', 'fk_subscription_periods_paid_by_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()->nullOnDelete();
        });

        DB::table('subscription_periods as p')
            ->join('orders as o', 'o.id', '=', 'p.order_id')
            ->whereNull('p.paid_by_user_id')
            ->where('p.kind', '!=', 'refund')
            ->update(['p.paid_by_user_id' => DB::raw('o.payer_user_id')]);
    }

    public function down(): void
    {
        Schema::table('subscription_periods', function (Blueprint $table) {
            $table->dropForeign('fk_subscription_periods_paid_by_user_id');
            $table->dropIndex('idx_subscription_periods_paid_by');
            $table->dropColumn('paid_by_user_id');
        });
    }
};
