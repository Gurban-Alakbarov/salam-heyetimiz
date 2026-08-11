<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Arch §5.6 — payment_callbacks (durable idempotency for at-least-once bank callbacks, R-PAY-06).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_callbacks', function (Blueprint $table) {
            $table->id();
            $table->string('bank_order_id', 80);
            $table->string('bank_status', 40);
            $table->char('payload_hash', 64);
            $table->unsignedBigInteger('order_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->enum('outcome', ['applied', 'duplicate', 'signature_invalid', 'unmatched', 'error']);
            $table->timestamp('created_at', 3)->useCurrent();

            $table->unique('payload_hash', 'uq_payment_callbacks_payload');
            $table->unique(['bank_order_id', 'bank_status'], 'uq_payment_callbacks_bank_status');
            $table->index('order_id', 'idx_payment_callbacks_order');
            $table->index(['outcome', 'created_at'], 'idx_payment_callbacks_outcome_time');

            $table->foreign('order_id', 'fk_payment_callbacks_order_id')
                ->references('id')->on('orders')
                ->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_callbacks');
    }
};
