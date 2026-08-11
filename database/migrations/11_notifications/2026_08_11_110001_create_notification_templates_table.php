<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Arch §7.1 — notification_templates. Admin-editable catalogue; localized bodies live in
// notification_template_locales. `default_channels_mask` bitmask: push=1, sms=2, inapp=4, email=8.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('template_key', 80);
            $table->unsignedTinyInteger('default_channels_mask');
            $table->enum('category', ['security', 'billing', 'operational', 'marketing']);
            $table->boolean('is_user_mutable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('template_key', 'uq_notification_templates_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
