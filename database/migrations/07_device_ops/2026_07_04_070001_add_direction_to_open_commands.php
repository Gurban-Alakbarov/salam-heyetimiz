<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4 (DeviceComm open pipeline): the open_commands aggregate becomes a generic relay-command
 * ledger.
 *
 *  - `direction` (open|close) — one row per relay press; the driver reads the model's open_command /
 *    close_command by this direction. Existing rows (open-only) default to 'open'.
 *  - user_id / device_user_id relaxed to NULL — admin "Test Relay" issues a command against a device
 *    that has no roster/subscription (source='admin'); the mobile flow always sets both.
 *
 * open_commands is RANGE-partitioned on partition_key, so this is a raw ALTER (Blueprint change() does
 * not play well with the partitioning). The table is append-style and empty in every environment where
 * this runs first, so ADD/MODIFY is instant.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE `open_commands`
                ADD COLUMN `direction` ENUM('open','close') NOT NULL DEFAULT 'open' AFTER `driver`,
                MODIFY COLUMN `user_id` BIGINT UNSIGNED NULL,
                MODIFY COLUMN `device_user_id` BIGINT UNSIGNED NULL"
        );
    }

    public function down(): void
    {
        // Only drop the added column. Restoring NOT NULL on user_id/device_user_id could fail if any
        // admin-sourced rows (NULL user) exist, so nullability is intentionally left relaxed on rollback.
        DB::statement('ALTER TABLE `open_commands` DROP COLUMN `direction`');
    }
};
