<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Arch §3.1 — devices. whitelist_capacity_used is DEPRECATED (compute on read,
// R-DOM-14); sim_* columns are Phase-1 placeholders (HIGH-04).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('serial', 64);
            $table->unsignedSmallInteger('device_model_id');
            $table->string('firmware_version', 40)->nullable();
            $table->string('sim_phone', 20);
            $table->unsignedSmallInteger('sim_operator_id')->nullable();
            $table->string('sim_iccid', 22)->nullable();
            $table->enum('driver_type', ['traccar', 'ble', 'sms'])->default('traccar'); // v1.2 transport pivot
            $table->enum('status', ['unassigned', 'active', 'suspended', 'disabled', 'decommissioned'])->default('unassigned');
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->unsignedSmallInteger('region_id')->nullable();
            $table->string('location_label', 160)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('last_online_at')->nullable();
            $table->tinyInteger('last_signal_strength')->nullable(); // 3GPP 0–31
            $table->unsignedSmallInteger('consecutive_offline_diagnostics')->default(0);
            $table->unsignedSmallInteger('whitelist_capacity_used')->default(0); // DEPRECATED (HIGH-05)
            $table->integer('sim_credit_minor')->nullable(); // placeholder (HIGH-04)
            $table->timestamp('sim_credit_checked_at')->nullable();
            $table->enum('sim_status', ['active', 'low_credit', 'suspended', 'unknown'])->default('unknown');
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('registered_by_admin_id')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('decommissioned_at')->nullable();
            $table->string('decommission_reason', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();

            $table->unique('serial', 'uq_devices_serial');
            $table->unique('sim_phone', 'uq_devices_sim_phone');
            $table->index('owner_user_id', 'idx_devices_owner');
            $table->index('status', 'idx_devices_status');
            $table->index(['status', 'last_online_at'], 'idx_devices_status_last_online');
            $table->index(['region_id', 'status'], 'idx_devices_region_status');
            $table->index('device_model_id', 'idx_devices_model');

            $table->foreign('device_model_id', 'fk_devices_device_model_id')
                ->references('id')->on('device_models')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('sim_operator_id', 'fk_devices_sim_operator_id')
                ->references('id')->on('sim_operators')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('owner_user_id', 'fk_devices_owner_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('region_id', 'fk_devices_region_id')
                ->references('id')->on('regions')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('registered_by_admin_id', 'fk_devices_registered_by_admin_id')
                ->references('id')->on('admin_users')
                ->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('created_by_admin_id', 'fk_devices_created_by_admin_id')
                ->references('id')->on('admin_users')
                ->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('updated_by_admin_id', 'fk_devices_updated_by_admin_id')
                ->references('id')->on('admin_users')
                ->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
