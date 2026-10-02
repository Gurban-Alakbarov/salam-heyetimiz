<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IMPLEMENTATION_PLAN M2 (B4). Residential-complex membership: a resident belongs to a complex BEFORE (and
 * independently of) holding any device roster row — it is what lets them see/subscribe to the complex's
 * shared devices. Family members are NOT complex members (they only get the devices their head grants), so
 * they can never browse the complex (no privilege escalation). One active membership per (complex, user) via
 * the same generated-column pattern as device_users. New table — no backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complex_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('complex_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('role', ['resident'])->default('resident');
            $table->enum('status', ['active', 'removed'])->default('active');
            // Generated column must follow `status` (it references it).
            $table->unsignedTinyInteger('is_active')
                ->storedAs("CASE WHEN status = 'active' THEN 1 ELSE NULL END")
                ->nullable();
            $table->unsignedBigInteger('invitation_id')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->unsignedBigInteger('removed_by_user_id')->nullable();
            $table->unsignedBigInteger('removed_by_admin_id')->nullable();
            $table->timestamps();

            $table->unique(['complex_id', 'user_id', 'is_active'], 'uq_complex_members_active');
            $table->index(['user_id', 'status'], 'idx_complex_members_user_status');
            $table->index(['complex_id', 'status'], 'idx_complex_members_complex_status');

            $table->foreign('complex_id', 'fk_complex_members_complex_id')
                ->references('id')->on('complexes')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('user_id', 'fk_complex_members_user_id')
                ->references('id')->on('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('invitation_id', 'fk_complex_members_invitation_id')
                ->references('id')->on('invitations')->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('removed_by_user_id', 'fk_complex_members_removed_by_user_id')
                ->references('id')->on('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('removed_by_admin_id', 'fk_complex_members_removed_by_admin_id')
                ->references('id')->on('admin_users')->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complex_members');
    }
};
