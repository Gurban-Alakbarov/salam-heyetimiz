<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Visitor links reuse the open_commands relay ledger with a new provenance: source='visitor' (a barrier
 * opened via a shared visitor token, no roster user). Append the enum value — appending to the end of a
 * MariaDB ENUM is an instant metadata change (no table rebuild), which matters for the RANGE-partitioned
 * open_commands table.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE `open_commands`
                MODIFY COLUMN `source` ENUM('mobile','admin','automation','visitor') NOT NULL DEFAULT 'mobile'"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE `open_commands`
                MODIFY COLUMN `source` ENUM('mobile','admin','automation') NOT NULL DEFAULT 'mobile'"
        );
    }
};
