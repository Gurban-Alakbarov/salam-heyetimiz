<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// RBAC — append-only administrative audit trail (BACKEND §8.1). Fed by the generic AuditableEvent
// recorder plus explicit admin-action logging (login/logout/impersonation/CRUD/settings). No FKs:
// actors/targets may be soft-deleted; the snapshot must survive.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('actor_kind', 20)->default('system'); // admin | user | system
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_label', 160)->nullable();       // email/name snapshot
            $table->unsignedBigInteger('impersonator_admin_id')->nullable();
            $table->string('action', 100);
            $table->string('auditable_type', 120)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('payload')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['actor_kind', 'actor_id'], 'idx_audit_actor');
            $table->index('action', 'idx_audit_action');
            $table->index('created_at', 'idx_audit_created_at');
            $table->index(['auditable_type', 'auditable_id'], 'idx_audit_target');
            $table->index('impersonator_admin_id', 'idx_audit_impersonator');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
