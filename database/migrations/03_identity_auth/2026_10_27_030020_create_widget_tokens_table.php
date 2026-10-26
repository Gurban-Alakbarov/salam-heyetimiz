<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Home-screen widget tokens — a long-lived, OPEN-ONLY credential per install, so the "Qapını aç" widget keeps
 * working without the app's 15-minute access token (and without the widget ever touching the rotating refresh
 * token, whose reuse detection a background refresh would trip). Opaque, SHA-256 hashed at rest; one active
 * per (user, install); revoked on logout, account deletion and refresh-token theft.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widget_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('user_device_id');
            $table->char('token_hash', 64);
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason', 20)->nullable();

            $table->unique('token_hash', 'uq_widget_tokens_token_hash');
            $table->index(['user_id', 'user_device_id', 'revoked_at'], 'idx_widget_tokens_install');

            $table->foreign('user_id', 'fk_widget_tokens_user_id')
                ->references('id')->on('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('user_device_id', 'fk_widget_tokens_user_device_id')
                ->references('id')->on('user_devices')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widget_tokens');
    }
};
