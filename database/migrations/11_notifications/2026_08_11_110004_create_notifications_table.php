<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DB Arch §7.3 — notifications. Per-(user × channel) record: drives the in-app inbox and marks the
 * delivery outcome for push/sms/inapp/email. RANGE-partitioned monthly by partition_key (YYYYMM),
 * 12 forward partitions + a MAXVALUE catch-all (R-DOM-18; DB Arch §0.6). Retention (swept by cron):
 * 12 months for inapp, 90 days for other channels.
 *
 * Raw SQL for the two InnoDB partitioning constraints (identical to open_commands, §6.1):
 *   1. The PRIMARY KEY must contain every partitioning column → (id, partition_key), not id alone.
 *   2. Partitioned InnoDB tables MUST NOT carry foreign keys. The `user_id → users` and
 *      `campaign_id → notification_campaigns` FKs documented in §7.3/§7.5 are therefore enforced at
 *      the application layer (NotificationDispatcher only writes valid ids). Documented platform
 *      limitation. The dedupe UNIQUE (user_id, dedupe_key, channel) gains partition_key (required in
 *      every UNIQUE on a partitioned table); MySQL treats multiple NULLs as distinct, preserving the
 *      documented "WHERE dedupe_key NOT NULL" semantics — and job retries always land the same month.
 */
return new class extends Migration
{
    public function up(): void
    {
        $partitions = $this->buildPartitions(CarbonImmutable::now('UTC')->startOfMonth(), 12);

        DB::statement(<<<SQL
            CREATE TABLE `notifications` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `partition_key` INT UNSIGNED NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `campaign_id` BIGINT UNSIGNED NULL,
                `template_key` VARCHAR(80) NOT NULL,
                `channel` ENUM('push','sms','inapp','email') NOT NULL,
                `payload` JSON NOT NULL,
                `dedupe_key` VARCHAR(120) NULL,
                `status` ENUM('queued','sent','failed','read') NOT NULL DEFAULT 'queued',
                `failure_reason` VARCHAR(255) NULL,
                `sent_at` TIMESTAMP NULL,
                `read_at` TIMESTAMP NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`, `partition_key`),
                UNIQUE KEY `uq_notifications_dedupe` (`user_id`, `dedupe_key`, `channel`, `partition_key`),
                KEY `idx_notifications_user_channel_status` (`user_id`, `channel`, `status`),
                KEY `idx_notifications_user_created` (`user_id`, `created_at`),
                KEY `idx_notifications_status_created` (`status`, `created_at`),
                KEY `idx_notifications_campaign` (`campaign_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            PARTITION BY RANGE (`partition_key`) (
                {$partitions}
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
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
