<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O5 — the admin refund's Idempotency-Key is stored and enforced: one refund per (admin, key). A retry with the
 * same key gets the refund the first call created. Nullable: refunds requested before this have no key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->string('idempotency_key', 100)->nullable()->after('requested_by_admin_id');
            $table->unique(['requested_by_admin_id', 'idempotency_key'], 'uq_refunds_admin_idempotency');
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropUnique('uq_refunds_admin_idempotency');
            $table->dropColumn('idempotency_key');
        });
    }
};
