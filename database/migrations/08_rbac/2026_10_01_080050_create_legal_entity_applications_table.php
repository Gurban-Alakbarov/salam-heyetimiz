<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IMPLEMENTATION_PLAN M11 (B9). LEGAL person (residential complex / building) application. Separate table,
 * statuses and endpoints from the physical one. Approval (admin) creates the complex in ONE transaction
 * (complexes.legal_entity_application_id, B4); submission never creates anything. One PENDING application per
 * VÖEN (generated `is_pending`, R: duplicate applications). New table — no backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_entity_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('applicant_user_id');
            $table->string('complex_name', 160);
            $table->string('legal_name', 200);
            $table->char('voen', 10);
            $table->string('legal_address', 255);
            $table->string('contact_person_name', 120);
            $table->string('contact_phone', 20);
            $table->string('contact_email', 160);
            $table->string('address', 255);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('region_id')->nullable();
            $table->unsignedInteger('apartments_count')->nullable();
            $table->string('note', 1000)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            // Generated column must follow `status` (it references it).
            $table->unsignedTinyInteger('is_pending')
                ->storedAs("CASE WHEN status = 'pending' THEN 1 ELSE NULL END")
                ->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->unsignedBigInteger('complex_id')->nullable();
            $table->timestamps();

            $table->unique(['voen', 'is_pending'], 'uq_legal_entity_applications_pending_voen');
            $table->index(['applicant_user_id', 'status'], 'idx_legal_entity_applications_applicant');
            $table->index(['status', 'created_at'], 'idx_legal_entity_applications_status');

            $table->foreign('applicant_user_id', 'fk_legal_entity_applications_applicant')
                ->references('id')->on('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('region_id', 'fk_legal_entity_applications_region_id')
                ->references('id')->on('regions')->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('reviewed_by_admin_id', 'fk_legal_entity_applications_reviewed_by')
                ->references('id')->on('admin_users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('complex_id', 'fk_legal_entity_applications_complex_id')
                ->references('id')->on('complexes')->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_entity_applications');
    }
};
