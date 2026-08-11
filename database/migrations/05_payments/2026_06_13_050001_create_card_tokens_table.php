<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Arch §5.4 — card_tokens (future auto-renew; tokens never stored in clear, R-PAY-01).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->binary('bank_token_encrypted', 255); // VARBINARY(255), app-layer encrypted
            $table->string('pan_masked', 20);
            $table->string('card_brand', 20)->nullable();
            $table->unsignedTinyInteger('expiry_month')->nullable();
            $table->unsignedSmallInteger('expiry_year')->nullable();
            $table->enum('status', ['active', 'revoked', 'expired'])->default('active');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'bank_token_encrypted'], 'uq_card_tokens_user_token');
            $table->index(['user_id', 'status'], 'idx_card_tokens_user_status');

            $table->foreign('user_id', 'fk_card_tokens_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_tokens');
    }
};
