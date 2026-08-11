<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Arch §7.2 — notification_template_locales. Per-locale (az/ru/en) subject + body. Three rows
// per template once seeded. `updated_by_admin_id` is an audit snapshot (no FK per §7.2).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_template_locales', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('notification_template_id');
            $table->enum('locale', ['az', 'ru', 'en']);
            $table->string('subject', 160)->nullable();
            $table->text('body');
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamps();

            $table->unique(['notification_template_id', 'locale'], 'uq_template_locale');

            $table->foreign('notification_template_id', 'fk_ntl_template_id')
                ->references('id')->on('notification_templates')
                ->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_template_locales');
    }
};
