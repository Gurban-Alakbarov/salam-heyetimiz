<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Arch §1.4 — refresh_tokens (60-day, SHA-256 hashed, rotated — R-SEC-02).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('user_device_id');
            $table->char('token_hash', 64);
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->enum('revocation_reason', ['rotated', 'logout', 'password_change', 'admin', 'security', 'expired'])->nullable();
            $table->unsignedBigInteger('replaced_by_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->unique('token_hash', 'uq_refresh_tokens_token_hash');
            $table->index(['user_id', 'revoked_at'], 'idx_refresh_tokens_user_revoked');
            $table->index('expires_at', 'idx_refresh_tokens_expires_at');

            $table->foreign('user_id', 'fk_refresh_tokens_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('user_device_id', 'fk_refresh_tokens_user_device_id')
                ->references('id')->on('user_devices')
                ->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('replaced_by_id', 'fk_refresh_tokens_replaced_by_id')
                ->references('id')->on('refresh_tokens')
                ->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
