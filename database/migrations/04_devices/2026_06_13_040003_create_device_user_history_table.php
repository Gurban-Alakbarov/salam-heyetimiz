<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Arch §3.3 — device_user_history (append-only roster change log).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_user_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('event', ['added', 'revoked', 'role_changed', 're_added']);
            $table->enum('from_role', ['owner', 'user'])->nullable();
            $table->enum('to_role', ['owner', 'user'])->nullable();
            $table->enum('actor_kind', ['user', 'admin', 'system']);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('created_at', 3)->useCurrent();

            $table->index(['device_id', 'created_at'], 'idx_dev_user_hist_device_time');
            $table->index(['user_id', 'created_at'], 'idx_dev_user_hist_user_time');

            $table->foreign('device_id', 'fk_dev_user_hist_device_id')
                ->references('id')->on('devices')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('user_id', 'fk_dev_user_hist_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_user_history');
    }
};
