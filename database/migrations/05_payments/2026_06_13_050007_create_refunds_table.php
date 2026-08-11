<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Arch §5.7 — refunds (admin workflow intent; the financial result is the payments row).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedInteger('amount_minor');
            $table->string('reason', 255);
            $table->enum('status', ['requested', 'processing', 'approved', 'rejected', 'failed'])->default('requested');
            $table->unsignedBigInteger('requested_by_admin_id');
            $table->unsignedBigInteger('processed_by_admin_id')->nullable();
            $table->unsignedBigInteger('linked_payment_id')->nullable();
            $table->string('error_message', 255)->nullable();
            $table->timestamps();

            $table->index('order_id', 'idx_refunds_order');
            $table->index(['status', 'created_at'], 'idx_refunds_status_created');

            $table->foreign('order_id', 'fk_refunds_order_id')
                ->references('id')->on('orders')
                ->cascadeOnUpdate()->restrictOnDelete();
            // requested_by_admin_id is NOT NULL and admins are never hard-deleted (DB Arch §1.2),
            // so RESTRICT is used here instead of the §5.7 "SET NULL" (which is invalid on a
            // NOT NULL column). processed_by_admin_id is nullable and uses SET NULL.
            $table->foreign('requested_by_admin_id', 'fk_refunds_requested_by_admin_id')
                ->references('id')->on('admin_users')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('processed_by_admin_id', 'fk_refunds_processed_by_admin_id')
                ->references('id')->on('admin_users')
                ->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('linked_payment_id', 'fk_refunds_linked_payment_id')
                ->references('id')->on('payments')
                ->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
