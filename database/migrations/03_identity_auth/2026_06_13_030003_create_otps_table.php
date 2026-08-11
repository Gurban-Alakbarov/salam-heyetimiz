<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Arch §1.5 — otps (hashed codes; TTL 120s; max 5 attempts — R-SEC-03).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otps', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->char('code_hash', 64);
            $table->enum('purpose', ['login', 'recover', 'email_verify'])->default('login');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('issued_ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['phone', 'purpose'], 'idx_otps_phone_purpose');
            $table->index('expires_at', 'idx_otps_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otps');
    }
};
