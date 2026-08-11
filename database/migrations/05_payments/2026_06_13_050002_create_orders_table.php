<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Arch §5.1 — orders (payment intents).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40);
            $table->unsignedBigInteger('payer_user_id');
            $table->enum('purpose', ['device_sale', 'sub_main', 'sub_additional', 'sub_renewal', 'bundle']);
            $table->unsignedInteger('amount_minor');
            $table->char('currency', 3)->default('AZN');
            $table->enum('status', [
                'pending', 'authorising', 'paid', 'failed',
                'cancelled', 'refunded', 'partially_refunded', 'expired',
            ])->default('pending');
            $table->string('bank_order_id', 80)->nullable();
            $table->text('bank_redirect_url')->nullable();
            $table->string('idempotency_key', 60)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failed_reason', 200)->nullable();
            $table->text('return_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('reference', 'uq_orders_reference');
            $table->unique('bank_order_id', 'uq_orders_bank_order'); // nullable; multiple NULLs allowed
            $table->unique(['payer_user_id', 'idempotency_key'], 'uq_orders_idempotency');
            $table->index(['status', 'created_at'], 'idx_orders_status_created');
            $table->index(['payer_user_id', 'created_at'], 'idx_orders_payer_created');
            $table->index(['purpose', 'created_at'], 'idx_orders_purpose_created');

            $table->foreign('payer_user_id', 'fk_orders_payer_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
