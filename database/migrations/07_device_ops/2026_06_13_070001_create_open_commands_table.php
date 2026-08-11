<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DB Arch §6.1 — open_commands. The single most write-heavy table; RANGE-partitioned monthly by
 * partition_key (YYYYMM), 12 forward partitions + MAXVALUE catch-all (a monthly cron rolls one
 * forward, DB Arch §0.6).
 *
 * Raw SQL because of the two InnoDB partitioning constraints (identical to payment_logs, §5.5):
 *   1. The PRIMARY KEY must contain every partitioning column → (id, partition_key), not id alone.
 *   2. Partitioned InnoDB tables MUST NOT carry foreign keys. The device/user/device_user/
 *      subscription FKs documented in §6.1 are therefore enforced at the application layer
 *      (the OpenCommandService only ever writes valid ids). Documented platform limitation.
 */
return new class extends Migration
{
    public function up(): void
    {
        $partitions = $this->buildPartitions(CarbonImmutable::now('UTC')->startOfMonth(), 12);

        DB::statement(<<<SQL
            CREATE TABLE `open_commands` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `partition_key` INT UNSIGNED NOT NULL,
                `device_id` BIGINT UNSIGNED NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `device_user_id` BIGINT UNSIGNED NOT NULL,
                `subscription_id` BIGINT UNSIGNED NULL,
                `idempotency_key` VARCHAR(60) NULL,
                `driver` VARCHAR(20) NOT NULL,
                `state` ENUM('queued','dispatching','dispatched','opened','failed','expired') NOT NULL DEFAULT 'queued',
                `failure_reason` VARCHAR(120) NULL,
                `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `requested_at` TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                `dispatched_at` TIMESTAMP(3) NULL,
                `completed_at` TIMESTAMP(3) NULL,
                `latency_ms` INT UNSIGNED NULL,
                `source` ENUM('mobile','admin','automation') NOT NULL DEFAULT 'mobile',
                `client_app_version` VARCHAR(20) NULL,
                `client_ip` VARCHAR(45) NULL,
                `metadata` JSON NULL,
                `created_at` TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                PRIMARY KEY (`id`, `partition_key`),
                -- Partitioning requires the partition column in every UNIQUE key. MySQL treats
                -- multiple NULLs as distinct, so this still allows many NULL-key rows per user
                -- (= the documented "WHERE idempotency_key NOT NULL"); replays arrive same-month.
                UNIQUE KEY `uq_open_commands_idempotency` (`user_id`, `idempotency_key`, `partition_key`),
                KEY `idx_open_commands_device_time` (`device_id`, `requested_at`),
                KEY `idx_open_commands_user_time` (`user_id`, `requested_at`),
                KEY `idx_open_commands_state_time` (`state`, `requested_at`),
                KEY `idx_open_commands_partition_state` (`partition_key`, `state`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            PARTITION BY RANGE (`partition_key`) (
                {$partitions}
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('open_commands');
    }

    /** @return string comma-joined PARTITION clauses incl. a MAXVALUE catch-all. */
    private function buildPartitions(CarbonImmutable $start, int $months): string
    {
        $clauses = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $start->addMonths($i);
            $name = 'p'.$month->format('Ym');
            $boundary = (int) $month->addMonth()->format('Ym');
            $clauses[] = "                PARTITION `{$name}` VALUES LESS THAN ({$boundary})";
        }

        $clauses[] = '                PARTITION `pmax` VALUES LESS THAN MAXVALUE';

        return implode(",\n", $clauses);
    }
};
