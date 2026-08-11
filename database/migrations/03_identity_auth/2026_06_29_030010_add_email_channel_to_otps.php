<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Registration module — Phase 1 (additive, backward-compatible).
| 1) otps gains an email channel: `email` destination + `channel` (sms|email, default sms so every
|    existing row + the phone path is unchanged) and `phone` is relaxed to nullable (email rows carry none).
| 2) users reserves a nullable `password` column for the future Security/password-login feature
|    (unused by this module). See docs/registration/DATABASE_PLAN.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otps', function (Blueprint $table) {
            $table->string('email', 160)->nullable()->after('phone');
            $table->enum('channel', ['sms', 'email'])->default('sms')->after('purpose');
            $table->string('phone', 20)->nullable()->change();
            $table->index(['email', 'purpose'], 'idx_otps_email_purpose');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('password', 255)->nullable()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('otps', function (Blueprint $table) {
            $table->dropIndex('idx_otps_email_purpose');
            $table->dropColumn(['email', 'channel']);
            // Safe to restore NOT NULL only while no email-only rows exist (see DATABASE_PLAN §7).
            $table->string('phone', 20)->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password');
        });
    }
};
