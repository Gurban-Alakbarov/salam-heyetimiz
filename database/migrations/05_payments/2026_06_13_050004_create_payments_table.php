<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Arch §5.3 — payments (actual financial events). amount_minor signed: charge +, refund −.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('bank_transaction_id', 80)->nullable();
            $table->enum('type', ['charge', 'refund', 'reversal']);
            $table->integer('amount_minor'); // signed
            $table->char('currency', 3)->default('AZN');
            $table->enum('status', ['approved', 'declined', 'reversed', 'pending', 'error']);
            $table->string('card_brand', 20)->nullable();
            $table->char('card_last4', 4)->nullable();
            $table->unsignedBigInteger('card_token_id')->nullable();
            $table->text('raw_response_encrypted')->nullable(); // app-layer encrypted
            $table->timestamp('occurred_at', 3)->useCurrent();
            $table->timestamp('created_at')->nullable();

            $table->unique('bank_transaction_id', 'uq_payments_bank_tx'); // nullable
            $table->index('order_id', 'idx_payments_order');
            $table->index(['status', 'occurred_at'], 'idx_payments_status_time');
            $table->index(['type', 'occurred_at'], 'idx_payments_type_time');

            $table->foreign('order_id', 'fk_payments_order_id')
                ->references('id')->on('orders')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('card_token_id', 'fk_payments_card_token_id')
                ->references('id')->on('card_tokens')
                ->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
