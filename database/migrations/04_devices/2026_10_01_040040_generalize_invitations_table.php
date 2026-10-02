<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * IMPLEMENTATION_PLAN M1 (B3). Generalises the DORMANT `invitations` table (0 rows in production, no code path
 * writes it) into the single invitation engine for both kinds — no second invitation table:
 *  - kind: `family_member` (default; the original device/owner design) | `complex_resident` (Komendant → email)
 *  - complex/admin/email/name targeting columns, sha256 `token_hash` (plaintext is never stored),
 *    resend + revoke bookkeeping, `family_link_id` (FK added with family_links in B8).
 *  - device_id / invited_by_user_id / invitee_phone / token become NULLABLE (complex invites have no device,
 *    are sent by an admin, keyed by email, and use token_hash).
 *
 * Safety: both directions refuse to run unless the table is empty — the pre-flight the plan requires, and the
 * guarantee that down() never strands rows that the narrower schema cannot hold.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->assertEmpty('up');

        Schema::table('invitations', function (Blueprint $table) {
            $table->enum('kind', ['family_member', 'complex_resident'])->default('family_member')->after('id');
            $table->unsignedBigInteger('device_id')->nullable()->change();
            $table->unsignedBigInteger('complex_id')->nullable()->after('device_id');
            $table->unsignedBigInteger('invited_by_user_id')->nullable()->change();
            $table->unsignedBigInteger('invited_by_admin_id')->nullable()->after('invited_by_user_id');
            $table->string('invitee_phone', 20)->nullable()->change();
            $table->string('invitee_email', 160)->nullable()->after('invitee_phone');
            $table->string('invitee_first_name', 60)->nullable()->after('invitee_email');
            $table->string('invitee_last_name', 60)->nullable()->after('invitee_first_name');
            $table->char('token', 40)->nullable()->change();
            $table->char('token_hash', 64)->nullable()->after('token');
            $table->unsignedSmallInteger('send_count')->default(0)->after('expires_at');
            $table->timestamp('last_sent_at')->nullable()->after('send_count');
            $table->timestamp('revoked_at')->nullable()->after('accepted_device_user_id');
            $table->unsignedBigInteger('revoked_by_user_id')->nullable()->after('revoked_at');
            $table->unsignedBigInteger('revoked_by_admin_id')->nullable()->after('revoked_by_user_id');
            $table->unsignedBigInteger('family_link_id')->nullable()->after('revoked_by_admin_id'); // FK in B8

            $table->unique('token_hash', 'uq_invitations_token_hash');
            // One live invitation per (complex, email) and per (inviting user, email). NULLs never collide, so
            // the complex rule ignores family rows and vice-versa.
            $table->unique(['kind', 'complex_id', 'invitee_email', 'is_pending'], 'uq_invitations_active_complex_email');
            $table->unique(['kind', 'invited_by_user_id', 'invitee_email', 'is_pending'], 'uq_invitations_active_family_email');
            $table->index('invitee_email', 'idx_invitations_invitee_email');

            $table->foreign('complex_id', 'fk_invitations_complex_id')
                ->references('id')->on('complexes')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('invited_by_admin_id', 'fk_invitations_invited_by_admin_id')
                ->references('id')->on('admin_users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('revoked_by_user_id', 'fk_invitations_revoked_by_user_id')
                ->references('id')->on('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('revoked_by_admin_id', 'fk_invitations_revoked_by_admin_id')
                ->references('id')->on('admin_users')->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        $this->assertEmpty('down');

        // Restore NOT NULL first (the only step that can fail). MariaDB refuses to tighten a column that a
        // foreign key uses (1832), so the two original FKs are dropped and re-created with their original rules.
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropForeign('fk_invitations_device_id');
            $table->dropForeign('fk_invitations_invited_by_user_id');
        });
        Schema::table('invitations', function (Blueprint $table) {
            $table->unsignedBigInteger('device_id')->nullable(false)->change();
            $table->unsignedBigInteger('invited_by_user_id')->nullable(false)->change();
            $table->string('invitee_phone', 20)->nullable(false)->change();
            $table->char('token', 40)->nullable(false)->change();
            $table->foreign('device_id', 'fk_invitations_device_id')
                ->references('id')->on('devices')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('invited_by_user_id', 'fk_invitations_invited_by_user_id')
                ->references('id')->on('users')->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->dropForeign('fk_invitations_complex_id');
            $table->dropForeign('fk_invitations_invited_by_admin_id');
            $table->dropForeign('fk_invitations_revoked_by_user_id');
            $table->dropForeign('fk_invitations_revoked_by_admin_id');
            $table->dropUnique('uq_invitations_token_hash');
            $table->dropUnique('uq_invitations_active_complex_email');
            $table->dropUnique('uq_invitations_active_family_email');
            $table->dropIndex('idx_invitations_invitee_email');
            $table->dropColumn([
                'kind', 'complex_id', 'invited_by_admin_id', 'invitee_email', 'invitee_first_name', 'invitee_last_name',
                'token_hash', 'send_count', 'last_sent_at', 'revoked_at', 'revoked_by_user_id', 'revoked_by_admin_id',
                'family_link_id',
            ]);
        });
    }

    private function assertEmpty(string $direction): void
    {
        $rows = DB::table('invitations')->count();
        if ($rows > 0) {
            throw new RuntimeException("invitations has {$rows} row(s); refusing to run the generalisation migration ({$direction}). Expected the dormant table to be empty.");
        }
    }
};
