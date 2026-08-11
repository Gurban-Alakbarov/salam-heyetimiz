<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DB Arch §6.3 — whitelist_changes. Outbox of pending whitelist mutations, drained per device in
 * (priority ASC, seq ASC) order by the WhitelistSyncJob (GSM batch). Never applied inline (R-GSM-07).
 *
 * `seq` is the monotonic ordering key independent of created_at (MED-09). MariaDB allows a single
 * AUTO_INCREMENT column (the PK `id`), so `seq` is populated = `id` immediately after insert by
 * WhitelistService — strictly monotonic and burst-safe, matching the documented intent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whitelist_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seq')->default(0); // set = id post-insert (monotonic drain key)
            $table->unsignedBigInteger('device_id');
            $table->unsignedBigInteger('device_user_id')->nullable();
            $table->enum('action', ['add', 'remove', 'clear']);
            $table->string('phone', 20);
            $table->unsignedTinyInteger('priority')->default(50); // 10 = admin resync, 50 = routine
            $table->enum('status', ['pending', 'in_progress', 'synced', 'failed', 'cancelled'])->default('pending');
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->string('last_error', 255)->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->unsignedBigInteger('requested_by_admin_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['device_id', 'status', 'priority', 'seq'], 'idx_whitelist_device_status_next');
            $table->index(['status', 'next_attempt_at'], 'idx_whitelist_status_next');

            $table->foreign('device_id', 'fk_whitelist_changes_device_id')
                ->references('id')->on('devices')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('device_user_id', 'fk_whitelist_changes_device_user_id')
                ->references('id')->on('device_users')
                ->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('requested_by_user_id', 'fk_whitelist_changes_requested_by_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('requested_by_admin_id', 'fk_whitelist_changes_requested_by_admin_id')
                ->references('id')->on('admin_users')
                ->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whitelist_changes');
    }
};
