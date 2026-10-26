<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * O13 (decided 2026-10-27) — a user may switch off the push of the visitor-opened notice and of admin
 * announcements; subscription (billing) reminders stay forced. Data-only: the seeder sets the same flags for
 * fresh databases, this flips the rows that already exist (copy edited by admins is untouched).
 */
return new class extends Migration
{
    private const KEYS = ['device.opened', 'system.admin_campaign'];

    public function up(): void
    {
        DB::table('notification_templates')->whereIn('template_key', self::KEYS)->update(['is_user_mutable' => true]);
    }

    public function down(): void
    {
        DB::table('notification_templates')->whereIn('template_key', self::KEYS)->update(['is_user_mutable' => false]);
    }
};
