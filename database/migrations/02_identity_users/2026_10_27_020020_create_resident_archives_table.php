<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O10 — an account an ADMIN deletes from the system is not lost: before the users row is anonymised (Y7 — the
 * phone / email are free to register again) its personal data and a snapshot of what it had (devices, roles,
 * subscriptions, complexes, family) are copied here. Kept for privacy.resident_archive_years (5), then purged
 * by `residents:purge-archive`. Self-service deletions (DELETE /v1/me) are NOT archived — they stay erased.
 * No FK on user_id: the archive must outlive anything that happens to the (anonymised) users row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resident_archives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('phone', 20)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('full_name', 120)->nullable();
            $table->string('account_type', 20)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->unsignedBigInteger('deleted_by_admin_id')->nullable();
            $table->string('reason', 40);
            $table->json('snapshot')->nullable();
            $table->timestamp('archived_at');
            $table->timestamp('purge_after');

            $table->index('user_id', 'idx_resident_archives_user');
            $table->index('archived_at', 'idx_resident_archives_archived_at');
            $table->index('purge_after', 'idx_resident_archives_purge_after');
            $table->index('phone', 'idx_resident_archives_phone');
            $table->index('email', 'idx_resident_archives_email');

            $table->foreign('deleted_by_admin_id', 'fk_resident_archives_deleted_by_admin_id')
                ->references('id')->on('admin_users')
                ->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_archives');
    }
};
