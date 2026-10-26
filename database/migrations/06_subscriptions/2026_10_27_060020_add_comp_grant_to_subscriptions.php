<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A3 — an admin's free grant is recorded on the subscription (when and by whom), so it is told apart from a
 * legacy complimentary row (price 0, never paid, no grant record) in the admin panel and reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('comp_granted_at')->nullable()->after('cancellation_reason');
            $table->unsignedBigInteger('comp_granted_by_admin_id')->nullable()->after('comp_granted_at');
            $table->foreign('comp_granted_by_admin_id', 'fk_subscriptions_comp_granted_by_admin_id')
                ->references('id')->on('admin_users')->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign('fk_subscriptions_comp_granted_by_admin_id');
            $table->dropColumn(['comp_granted_at', 'comp_granted_by_admin_id']);
        });
    }
};
