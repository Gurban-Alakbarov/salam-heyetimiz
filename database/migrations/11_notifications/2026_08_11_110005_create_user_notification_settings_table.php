<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Arch §7.4 — user_notification_settings. Per-user, per-(template, channel) mute preference for
// mutable categories only (security/billing are forced). Preferences UI is deferred (E6); the
// schema is created now so the domain can honour mutes when the UI ships.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notification_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('template_key', 80);
            $table->enum('channel', ['push', 'sms', 'inapp', 'email']);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'template_key', 'channel'], 'uq_unotif_user_template_channel');

            $table->foreign('user_id', 'fk_unotif_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_settings');
    }
};
