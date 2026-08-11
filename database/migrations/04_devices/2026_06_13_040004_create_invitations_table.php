<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Arch §3.4 — invitations. Active-target uniqueness via STORED is_pending +
// UNIQUE(device_id, invitee_phone, is_pending). The linked_order_id FK → orders
// is added in batch 11 (orders is batch 05); the column exists here without it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->unsignedBigInteger('invited_by_user_id');
            $table->string('invitee_phone', 20);
            $table->unsignedBigInteger('invitee_user_id')->nullable();
            $table->enum('role', ['user', 'owner'])->default('user');
            $table->enum('payer', ['owner', 'invitee'])->default('owner');
            $table->char('token', 40);
            $table->enum('status', ['pending', 'accepted', 'declined', 'expired', 'cancelled'])->default('pending');
            // Generated column must follow `status`.
            $table->unsignedTinyInteger('is_pending')
                ->storedAs("CASE WHEN status = 'pending' THEN 1 ELSE NULL END")
                ->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->unsignedBigInteger('accepted_device_user_id')->nullable();
            $table->unsignedBigInteger('linked_order_id')->nullable(); // FK added in batch 11
            $table->timestamps();

            $table->unique('token', 'uq_invitations_token');
            $table->unique(['device_id', 'invitee_phone', 'is_pending'], 'uq_invitations_active_target');
            $table->index(['status', 'expires_at'], 'idx_invitations_status_expires');
            $table->index('invitee_phone', 'idx_invitations_invitee_phone');

            $table->foreign('device_id', 'fk_invitations_device_id')
                ->references('id')->on('devices')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('invited_by_user_id', 'fk_invitations_invited_by_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('invitee_user_id', 'fk_invitations_invitee_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('accepted_device_user_id', 'fk_invitations_accepted_device_user_id')
                ->references('id')->on('device_users')
                ->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
