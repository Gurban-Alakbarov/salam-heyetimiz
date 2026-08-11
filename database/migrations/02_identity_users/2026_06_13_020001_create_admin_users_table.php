<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Arch §1.2 — admin_users. recovery_codes_hashes + recovery_codes_generated_at (CRIT-09).
// preferred_language added per the LOCALIZATION_SPECIFICATION reconciliation
// (PROJECT_CONSTITUTION Appendix A.3) — documentation fix, not a redesign.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_users', function (Blueprint $table) {
            $table->id();
            $table->string('email', 160);
            $table->string('password', 255);
            $table->string('name', 120);
            $table->enum('role', ['super_admin', 'technical'])->default('technical');
            $table->string('phone', 20)->nullable();
            $table->binary('totp_secret', 255)->nullable(); // VARBINARY(255), app-layer encrypted
            $table->boolean('is_2fa_enabled')->default(false);
            $table->timestamp('is_2fa_enforced_at')->nullable();
            $table->json('recovery_codes_hashes')->nullable(); // 8 bcrypt hashes; consumed → null
            $table->timestamp('recovery_codes_generated_at')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->unsignedSmallInteger('failed_login_count')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->enum('status', ['active', 'suspended', 'offboarded'])->default('active');
            $table->enum('preferred_language', ['az', 'ru', 'en'])->default('az');
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();

            $table->unique('email', 'uq_admin_users_email');
            $table->index('status', 'idx_admin_users_status');
            $table->index('role', 'idx_admin_users_role');

            $table->foreign('created_by_admin_id', 'fk_admin_users_created_by')
                ->references('id')->on('admin_users')
                ->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('updated_by_admin_id', 'fk_admin_users_updated_by')
                ->references('id')->on('admin_users')
                ->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_users');
    }
};
