<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Arch §1.7 — user_consents (append-only; AZ Personal Data Law).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_consents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('consent_kind', ['terms', 'privacy', 'marketing_push', 'marketing_sms', 'data_processing']);
            $table->string('document_version', 20);
            $table->boolean('granted');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'consent_kind', 'created_at'], 'idx_user_consents_user_kind');

            $table->foreign('user_id', 'fk_user_consents_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_consents');
    }
};
