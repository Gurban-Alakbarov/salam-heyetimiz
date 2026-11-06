<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Settings no code ever read. Traccar and SMS run from .env, so their old credentials must not stay behind here.
return new class extends Migration
{
    private const GROUPS = ['sms', 'traccar'];

    private const KEEP = ['traccar.offline_threshold_minutes'];

    private const KEYS = [
        'general.favicon_url',
        'general.default_language',
        'general.force_update',
        'general.latest_version',
        'general.logo_url',
        'general.timezone',
        'security.max_sessions',
        'security.session_timeout_minutes',
        'email.enable_email_otp',
        'email.subject_email_changed',
        'email.subject_password_changed',
        'email.subject_password_set',
        'email.subject_security_alert',
        'email.subject_welcome',
        'email.tpl_welcome',
        'otp.brute_force_protection',
        'otp.enable_email_otp',
        'otp.enable_sms_otp',
        'otp.tpl_password_reset',
        'payments.enable_logging',
        'payments.recurring_enabled',
        'payments.save_card_enabled',
        'subscriptions.auto_renew_enabled',
        'subscriptions.default_trial_days',
        'subscriptions.expiration_reminder_days',
        'subscriptions.grace_period_days',
        'subscriptions.renewal_reminder_days',
    ];

    public function up(): void
    {
        DB::table('settings')->whereIn('key', self::KEYS)->delete();

        foreach (self::GROUPS as $group) {
            DB::table('settings')->where('key', 'like', $group.'.%')->whereNotIn('key', self::KEEP)->delete();
        }
    }

    public function down(): void
    {
        // None of these values was ever used; there is nothing to restore.
    }
};
