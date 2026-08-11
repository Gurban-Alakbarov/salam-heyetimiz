<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every visitor open attempt — the security audit trail (time, IP, User-Agent, outcome). Correlated to
 * the physical relay command via open_command_id (plain column: open_commands is partitioned, so no FK).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitor_link_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_link_id')->constrained('visitor_links')->cascadeOnDelete();
            $table->unsignedBigInteger('open_command_id')->nullable(); // no FK — open_commands is partitioned
            $table->timestamp('used_at');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->enum('result', ['accepted', 'expired', 'revoked', 'usage_exceeded', 'device_inactive', 'not_found']);
            $table->timestamp('created_at')->useCurrent();

            $table->index('visitor_link_id', 'idx_visitor_usages_link');
            $table->index('used_at', 'idx_visitor_usages_used_at');
            $table->index('open_command_id', 'idx_visitor_usages_command');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_link_usages');
    }
};
