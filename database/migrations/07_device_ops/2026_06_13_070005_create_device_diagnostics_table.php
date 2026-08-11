<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DB Arch §6.2 — device_diagnostics. Periodic/event device health reports; under v1.2 the data source is
 * Traccar telemetry event-forward (was a CLIP/SMS driver ping). RANGE-partitioned monthly by partition_key
 * (YYYYMM), raw SQL — same partitioning constraints as open_commands (PK includes partition_key; no FK;
 * device_id → devices is app-enforced).
 */
return new class extends Migration
{
    public function up(): void
    {
        $partitions = $this->buildPartitions(CarbonImmutable::now('UTC')->startOfMonth(), 12);

        DB::statement(<<<SQL
            CREATE TABLE `device_diagnostics` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `partition_key` INT UNSIGNED NOT NULL,
                `device_id` BIGINT UNSIGNED NOT NULL,
                `source` ENUM('scheduled_ping','open_dispatch','admin_ping','device_initiated') NOT NULL,
                `online` TINYINT(1) NOT NULL,
                `signal_strength` TINYINT NULL,
                `battery_level` TINYINT NULL,
                `firmware_version` VARCHAR(40) NULL,
                `raw` JSON NULL,
                `reported_at` TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                PRIMARY KEY (`id`, `partition_key`),
                KEY `idx_device_diagnostics_device_time` (`device_id`, `reported_at`),
                KEY `idx_device_diagnostics_online_time` (`online`, `reported_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            PARTITION BY RANGE (`partition_key`) (
                {$partitions}
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('device_diagnostics');
    }

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
