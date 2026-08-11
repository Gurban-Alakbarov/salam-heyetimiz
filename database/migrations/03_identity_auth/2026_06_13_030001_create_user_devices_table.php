<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Arch §1.3 — user_devices (one row per mobile install; FCM push tokens).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->char('install_uuid', 36);
            $table->enum('platform', ['ios', 'android']);
            $table->string('os_version', 40)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->string('device_model', 80)->nullable();
            $table->string('push_token', 255)->nullable();
            $table->timestamp('push_token_updated_at')->nullable();
            $table->boolean('push_invalid')->default(false);
            $table->boolean('biometric_enrolled')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_seen_ip', 45)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'install_uuid'], 'uq_user_devices_user_install');
            $table->index('push_token', 'idx_user_devices_push_token');
            $table->index(['user_id', 'revoked_at'], 'idx_user_devices_user_revoked');

            $table->foreign('user_id', 'fk_user_devices_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
