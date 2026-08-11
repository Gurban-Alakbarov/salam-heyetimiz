<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visitor links — a temporary, shareable open-grant for a barrier (couriers, taxis, guests…). The
 * secret token is NEVER stored: only its sha256 hash. Bounds are two primitives (max_usage + expires_at)
 * that the resident-facing access_type maps onto. Dedicated table — no temporary data on `devices`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitor_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();

            // Exactly one creator is set (resident OR admin) — mirrors device_users' added_by_* pattern.
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();

            $table->char('token_hash', 64)->unique();          // sha256(plaintext) — plaintext shown once, never stored
            $table->string('token_prefix', 12)->nullable();    // non-secret first chars, for admin display/search
            $table->string('visitor_name', 120)->nullable();
            $table->string('purpose', 32)->nullable();          // optional self-declared reason (VisitorPurpose) — reporting only

            $table->enum('access_type', ['one_time', 'time_limited']);
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('max_usage')->nullable();  // one_time = 1, time_limited = null (unlimited in window)
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();

            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revoked_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('device_id', 'idx_visitor_links_device');
            $table->index('created_by_user_id', 'idx_visitor_links_creator_user');
            $table->index('expires_at', 'idx_visitor_links_expires');
            $table->index('revoked_at', 'idx_visitor_links_revoked');
            $table->index('purpose', 'idx_visitor_links_purpose'); // reporting/analytics grouping
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_links');
    }
};
