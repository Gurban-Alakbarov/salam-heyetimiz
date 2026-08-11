<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Arch §2.2 — device_models. whitelist_capacity is NOT NULL default 100 (HIGH-05);
// fallback_open_driver added v1.1 (HIGH-03).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_models', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('vendor', 60);
            $table->string('model_code', 60);
            $table->boolean('supports_clip')->default(true);
            $table->boolean('supports_sms')->default(true);
            $table->boolean('supports_mqtt')->default(false);
            $table->enum('default_driver_type', ['traccar', 'ble', 'sms'])->default('traccar'); // v1.2 transport pivot
            $table->enum('fallback_open_driver', ['traccar', 'ble', 'sms'])->nullable();
            $table->unsignedSmallInteger('whitelist_capacity')->default(100);
            $table->string('sms_open_command', 40)->nullable();
            $table->string('notes', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['vendor', 'model_code'], 'uq_device_models_vendor_model');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_models');
    }
};
