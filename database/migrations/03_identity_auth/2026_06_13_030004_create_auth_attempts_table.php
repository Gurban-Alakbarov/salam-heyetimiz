<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Arch §1.6 — auth_attempts (append-only brute-force/abuse detection).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_attempts', function (Blueprint $table) {
            $table->id();
            $table->enum('actor_kind', ['user', 'admin']);
            $table->string('identifier', 160); // phone (user) or email (admin)
            $table->enum('outcome', [
                'success', 'wrong_credential', 'locked', 'rate_limited',
                'otp_expired', 'otp_max_attempts', '2fa_failed',
            ]);
            $table->string('ip', 45);
            $table->string('user_agent', 255)->nullable();
            $table->char('request_id', 26)->nullable();
            $table->timestamp('created_at', 3)->useCurrent();

            $table->index(['identifier', 'created_at'], 'idx_auth_attempts_identifier_time');
            $table->index(['ip', 'created_at'], 'idx_auth_attempts_ip_time');
            $table->index(['outcome', 'created_at'], 'idx_auth_attempts_outcome_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_attempts');
    }
};
