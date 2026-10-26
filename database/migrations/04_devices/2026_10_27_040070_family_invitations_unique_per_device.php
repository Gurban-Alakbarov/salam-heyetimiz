<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A9 — a family head may have one live invitation per (person, DEVICE), not one per person: a pending invite
 * to the first gate no longer blocks inviting the same person to a second gate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->unique(['kind', 'invited_by_user_id', 'invitee_email', 'device_id', 'is_pending'], 'uq_invitations_active_family_device');
            $table->dropUnique('uq_invitations_active_family_email');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->unique(['kind', 'invited_by_user_id', 'invitee_email', 'is_pending'], 'uq_invitations_active_family_email');
            $table->dropUnique('uq_invitations_active_family_device');
        });
    }
};
