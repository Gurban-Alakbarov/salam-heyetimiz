<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Admin audit phase 3 (2026-10-29): a decommissioned device is a soft-deleted row kept for history, but the old
// unique indexes on serial (IMEI) and sim_phone also counted it — a mistyped or decommissioned IMEI / SIM could never
// be registered again without DB surgery. Uniqueness now covers ACTIVE rows only, through stored generated columns
// that are NULL once the row is soft-deleted (MariaDB/MySQL allow many NULLs in a unique index). Plain indexes keep
// the lookups by serial / SIM fast.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('serial_active', 64)->nullable()->storedAs('IF(`deleted_at` IS NULL, `serial`, NULL)');
            $table->string('sim_phone_active', 20)->nullable()->storedAs('IF(`deleted_at` IS NULL, `sim_phone`, NULL)');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropUnique('uq_devices_serial');
            $table->dropUnique('uq_devices_sim_phone');
            $table->unique('serial_active', 'uq_devices_serial_active');
            $table->unique('sim_phone_active', 'uq_devices_sim_phone_active');
            $table->index('serial', 'idx_devices_serial');
            $table->index('sim_phone', 'idx_devices_sim_phone');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropUnique('uq_devices_serial_active');
            $table->dropUnique('uq_devices_sim_phone_active');
            $table->dropIndex('idx_devices_serial');
            $table->dropIndex('idx_devices_sim_phone');
        });
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['serial_active', 'sim_phone_active']);
            // fails if an IMEI / SIM was re-used meanwhile — by design (that data must be resolved first)
            $table->unique('serial', 'uq_devices_serial');
            $table->unique('sim_phone', 'uq_devices_sim_phone');
        });
    }
};
