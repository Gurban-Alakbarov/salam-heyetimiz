<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Arch §4.1 — subscriptions. One logical sub per device_user (renewals extend ends_at;
// term history lives in subscription_periods). latest_order_id removed (HIGH-18, derived).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_user_id');
            $table->enum('tier', ['main', 'additional']);
            $table->unsignedInteger('price_minor');
            $table->char('currency', 3)->default('AZN');
            $table->unsignedSmallInteger('term_days')->default(365);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->enum('status', ['pending_payment', 'active', 'expired', 'cancelled', 'refunded'])->default('pending_payment');
            $table->boolean('auto_renew')->default(false);
            $table->unsignedBigInteger('card_token_id')->nullable();
            $table->enum('last_reminder_kind', ['d30', 'd15', 'd7', 'd1', 'expired'])->nullable();
            $table->timestamp('last_reminder_sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 255)->nullable();
            $table->timestamps();

            $table->unique('device_user_id', 'uq_subscriptions_device_user');
            $table->index(['status', 'ends_at'], 'idx_subscriptions_status_ends');
            $table->index('ends_at', 'idx_subscriptions_ends_at');
            $table->index(['auto_renew', 'ends_at'], 'idx_subscriptions_auto_renew');

            $table->foreign('device_user_id', 'fk_subscriptions_device_user_id')
                ->references('id')->on('device_users')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('card_token_id', 'fk_subscriptions_card_token_id')
                ->references('id')->on('card_tokens')
                ->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
