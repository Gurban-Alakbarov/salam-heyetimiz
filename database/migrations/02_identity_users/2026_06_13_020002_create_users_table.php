<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Arch §1.1 — users. phone is plain UNIQUE; soft-deleted rows carry an
// anonymised phone so the real number is reusable (HIGH-12 / R-DOM-02).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->char('phone_country', 2)->default('AZ');
            $table->string('full_name', 120)->nullable();
            $table->string('email', 160)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->enum('preferred_language', ['az', 'ru', 'en'])->default('az');
            $table->enum('status', ['active', 'blocked', 'self_deleted'])->default('active');
            $table->string('blocked_reason', 255)->nullable();
            $table->unsignedBigInteger('blocked_by_admin_id')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('phone', 'uq_users_phone');
            $table->unique('email', 'uq_users_email');
            $table->index(['status', 'created_at'], 'idx_users_status_created_at');
            $table->index('last_login_at', 'idx_users_last_login_at');

            $table->foreign('blocked_by_admin_id', 'fk_users_blocked_by_admin_id')
                ->references('id')->on('admin_users')
                ->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
