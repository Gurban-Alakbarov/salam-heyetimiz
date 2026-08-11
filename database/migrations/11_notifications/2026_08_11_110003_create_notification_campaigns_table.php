<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Arch §7.5 (Reconciliation §C) — notification_campaigns. One row per admin-initiated send
// (Tech Spec §17.6). Fixed push+inapp delivery — there is NO channels_mask (the channel bitmask is
// a template-level selector only, R-NOT-04). Automatic business notifications do not use this table.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_campaigns', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('created_by_admin_id');
            $table->string('type', 40); // MVP: 'system'
            $table->string('title', 160);
            $table->text('body');
            $table->enum('language', ['az', 'ru', 'en']);
            $table->enum('audience_scope', ['all_users', 'user_ids', 'filter', 'complex']);
            $table->json('audience_filter')->nullable();
            $table->enum('status', ['draft', 'queued', 'sending', 'sent', 'failed'])->default('draft');
            $table->unsignedInteger('total_recipients')->nullable();
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['created_by_admin_id', 'created_at'], 'idx_campaigns_created');
            $table->index('status', 'idx_campaigns_status');

            $table->foreign('created_by_admin_id', 'fk_ncampaigns_admin_id')
                ->references('id')->on('admin_users')
                ->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_campaigns');
    }
};
