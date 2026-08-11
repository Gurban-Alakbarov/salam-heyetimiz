<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Arch §3.2 — device_users. Active-uniqueness via the STORED generated
// `is_active` column + UNIQUE(device_id, user_id, is_active); NULLs don't collide
// so a user may be re-added after revocation (R-DOM-10 / HIGH-07). The Plan-B
// fallback (mirror table) is invoked only if ci:device-users-uniqueness fails.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('role', ['owner', 'user'])->default('user');
            $table->unsignedBigInteger('added_by_user_id')->nullable();
            $table->unsignedBigInteger('added_by_admin_id')->nullable();
            $table->time('access_window_start')->nullable();  // [P2]
            $table->time('access_window_end')->nullable();    // [P2]
            $table->unsignedTinyInteger('access_days_mask')->nullable(); // [P2] Mon=1..Sun=64
            $table->enum('status', ['active', 'revoked'])->default('active');
            // Generated column must follow `status` (it references it).
            $table->unsignedTinyInteger('is_active')
                ->storedAs("CASE WHEN status = 'active' THEN 1 ELSE NULL END")
                ->nullable();
            $table->timestamp('last_open_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('revoked_by_user_id')->nullable();
            $table->unsignedBigInteger('revoked_by_admin_id')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'user_id', 'is_active'], 'uq_device_users_active');
            $table->index(['user_id', 'status'], 'idx_device_users_user_status');
            $table->index(['device_id', 'status'], 'idx_device_users_device_status');
            $table->index(['device_id', 'role'], 'idx_device_users_role');

            $table->foreign('device_id', 'fk_device_users_device_id')
                ->references('id')->on('devices')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('user_id', 'fk_device_users_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('added_by_user_id', 'fk_device_users_added_by_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('revoked_by_user_id', 'fk_device_users_revoked_by_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('added_by_admin_id', 'fk_device_users_added_by_admin_id')
                ->references('id')->on('admin_users')
                ->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('revoked_by_admin_id', 'fk_device_users_revoked_by_admin_id')
                ->references('id')->on('admin_users')
                ->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_users');
    }
};
