<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DB Arch §5.5 — payment_logs. Append-only, RANGE-partitioned monthly by partition_key
 * (YYYYMM), provisioned with 12 forward partitions + a MAXVALUE catch-all; a monthly cron
 * rolls one forward (DB Arch §0.6).
 *
 * Built via raw SQL because of two partitioning constraints that Laravel's Blueprint
 * cannot express:
 *   1. The PRIMARY KEY must contain every column of the partitioning function, so it is
 *      (id, partition_key) — not id alone.
 *   2. InnoDB partitioned tables MUST NOT carry foreign keys. The order_id → orders FK
 *      documented in §5.5 is therefore enforced at the application layer (PaymentLogger
 *      only ever writes a valid order_id or NULL; orders are never hard-deleted). This is a
 *      documented platform limitation, not a schema redesign.
 */
return new class extends Migration
{
    public function up(): void
    {
        $partitions = $this->buildPartitions(CarbonImmutable::now('UTC')->startOfMonth(), 12);

        DB::statement(<<<SQL
            CREATE TABLE `payment_logs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `partition_key` INT UNSIGNED NOT NULL,
                `order_id` BIGINT UNSIGNED NULL,
                `direction` ENUM('outbound','inbound') NOT NULL,
                `endpoint` VARCHAR(200) NOT NULL,
                `http_method` VARCHAR(10) NULL,
                `http_status` SMALLINT UNSIGNED NULL,
                `request_redacted_encrypted` TEXT NULL,
                `response_redacted_encrypted` TEXT NULL,
                `signature_provided` TINYINT(1) NULL,
                `signature_valid` TINYINT(1) NULL,
                `ip` VARCHAR(45) NULL,
                `latency_ms` INT UNSIGNED NULL,
                `created_at` TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                PRIMARY KEY (`id`, `partition_key`),
                KEY `idx_payment_logs_order_time` (`order_id`, `created_at`),
                KEY `idx_payment_logs_direction_time` (`direction`, `created_at`),
                KEY `idx_payment_logs_signature_invalid` (`signature_valid`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            PARTITION BY RANGE (`partition_key`) (
                {$partitions}
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_logs');
    }

    /** @return string comma-joined PARTITION clauses incl. a MAXVALUE catch-all. */
    private function buildPartitions(CarbonImmutable $start, int $months): string
    {
        $clauses = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $start->addMonths($i);
            $next = $month->addMonth();
            $name = 'p'.$month->format('Ym');
            $boundary = (int) $next->format('Ym');
            $clauses[] = "                PARTITION `{$name}` VALUES LESS THAN ({$boundary})";
        }

        $clauses[] = '                PARTITION `pmax` VALUES LESS THAN MAXVALUE';

        return implode(",\n", $clauses);
    }
};
