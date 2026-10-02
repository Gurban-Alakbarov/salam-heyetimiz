<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IMPLEMENTATION_PLAN M3 (B8). The family RELATION layer ("is X a family member of head Y?"), kept apart from
 * device access (device_users.family_link_id) and from billing (each member's own subscription). One active
 * link per (head, member) via the generated-column pattern; a removed link stays as history and a re-invite
 * creates a new row. head ≠ member is enforced in FamilyService. device_users.family_link_id stays FK-less
 * (B4) so pre-existing rows are never constrained. New table — no backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('head_user_id');
            $table->unsignedBigInteger('member_user_id');
            $table->enum('status', ['active', 'removed'])->default('active');
            // Generated column must follow `status` (it references it).
            $table->unsignedTinyInteger('is_active')
                ->storedAs("CASE WHEN status = 'active' THEN 1 ELSE NULL END")
                ->nullable();
            $table->unsignedBigInteger('invitation_id')->nullable();
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->unsignedBigInteger('removed_by_user_id')->nullable();
            $table->unsignedBigInteger('removed_by_admin_id')->nullable();
            $table->timestamps();

            $table->unique(['head_user_id', 'member_user_id', 'is_active'], 'uq_family_links_active');
            $table->index(['head_user_id', 'status'], 'idx_family_links_head_status');
            $table->index(['member_user_id', 'status'], 'idx_family_links_member_status');

            $table->foreign('head_user_id', 'fk_family_links_head_user_id')
                ->references('id')->on('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('member_user_id', 'fk_family_links_member_user_id')
                ->references('id')->on('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('invitation_id', 'fk_family_links_invitation_id')
                ->references('id')->on('invitations')->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('removed_by_user_id', 'fk_family_links_removed_by_user_id')
                ->references('id')->on('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('removed_by_admin_id', 'fk_family_links_removed_by_admin_id')
                ->references('id')->on('admin_users')->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_links');
    }
};
