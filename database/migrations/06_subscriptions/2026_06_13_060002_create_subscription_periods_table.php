<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Arch §4.2 — subscription_periods. Append-only history of every paid term / refund.
// amount_minor is signed (refund negative). order_id → orders (batch 05).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id');
            $table->unsignedBigInteger('order_id');
            $table->enum('kind', ['initial', 'renewal', 'extension', 'refund']);
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->integer('amount_minor'); // signed
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subscription_id', 'period_end'], 'idx_subscription_periods_subscription');
            $table->index('order_id', 'idx_subscription_periods_order');

            $table->foreign('subscription_id', 'fk_subscription_periods_subscription_id')
                ->references('id')->on('subscriptions')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('order_id', 'fk_subscription_periods_order_id')
                ->references('id')->on('orders')
                ->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_periods');
    }
};
