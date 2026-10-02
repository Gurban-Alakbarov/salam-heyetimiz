<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IMPLEMENTATION_PLAN M10 (B9). PHYSICAL person application — a lead for a private-yard device (sale +
 * installation run through the existing private flow). Its own table / status set; never shared with the
 * legal-entity workflow. Location = latitude/longitude (OpenStreetMap pin). One OPEN application per user
 * (generated `is_open`). New table — no backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('individual_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('full_name', 120);
            $table->string('phone', 20);
            $table->string('email', 160);
            $table->string('address', 255);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('region_id')->nullable();
            $table->string('note', 1000)->nullable();
            $table->enum('status', ['new', 'contacted', 'in_progress', 'installed', 'rejected'])->default('new');
            // Generated column must follow `status` (it references it).
            $table->unsignedTinyInteger('is_open')
                ->storedAs("CASE WHEN status IN ('new','contacted','in_progress') THEN 1 ELSE NULL END")
                ->nullable();
            $table->string('admin_note', 1000)->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->unsignedBigInteger('handled_by_admin_id')->nullable();
            $table->timestamp('status_changed_at')->nullable();
            $table->unsignedBigInteger('device_id')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'is_open'], 'uq_individual_applications_open');
            $table->index(['status', 'created_at'], 'idx_individual_applications_status');

            $table->foreign('user_id', 'fk_individual_applications_user_id')
                ->references('id')->on('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('region_id', 'fk_individual_applications_region_id')
                ->references('id')->on('regions')->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('handled_by_admin_id', 'fk_individual_applications_handled_by')
                ->references('id')->on('admin_users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('device_id', 'fk_individual_applications_device_id')
                ->references('id')->on('devices')->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('individual_applications');
    }
};
