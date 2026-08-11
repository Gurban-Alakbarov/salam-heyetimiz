<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DB Arch §6.1.2 — open_command_feedback (v1.1, CRIT-06). Optional user-reported actuation
 * confirmation after CLIP-only opens. One feedback per (command, user).
 *
 * No FK to open_commands (RANGE-partitioned parent; InnoDB constraint) — app-enforced. The user_id
 * FK is a normal FK (users is not partitioned).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('open_command_feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('open_command_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('gate_moved');
            $table->string('comment', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['open_command_id', 'user_id'], 'uq_open_command_feedback_command_user');
            $table->index('open_command_id', 'idx_open_command_feedback_command');

            $table->foreign('user_id', 'fk_open_command_feedback_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_command_feedback');
    }
};
