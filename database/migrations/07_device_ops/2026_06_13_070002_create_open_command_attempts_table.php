<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DB Arch §6.1.1 — open_command_attempts (v1.1, HIGH-03). Per-attempt dispatch history; a primary
 * attempt is always recorded, a fallback (§12.7) appears as a second row.
 *
 * No FK to open_commands: the parent is RANGE-partitioned (InnoDB forbids FKs referencing a
 * partitioned table). The open_command_id → open_commands ON DELETE CASCADE documented in §6.1.1 is
 * app-enforced (attempts are written only for an existing command; open_commands is append-only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('open_command_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('open_command_id');
            $table->unsignedTinyInteger('attempt_no'); // 1 = primary, 2 = fallback
            $table->string('driver', 20);
            $table->string('voice_gateway_id', 40)->nullable();
            $table->enum('state', ['queued', 'dispatching', 'dispatched', 'opened', 'failed', 'expired'])->default('queued');
            $table->string('failure_reason', 120)->nullable();
            $table->timestamp('started_at', 3)->useCurrent();
            $table->timestamp('completed_at', 3)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->json('metadata')->nullable();

            $table->unique(['open_command_id', 'attempt_no'], 'uq_open_command_attempts');
            $table->index('open_command_id', 'idx_open_command_attempts_command');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_command_attempts');
    }
};
