<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v1.2 transport pivot — maps a platform device to its Traccar identity so the backend can send
 * commands and correlate forwarded telemetry. One Traccar device per platform device.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traccar_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->unsignedBigInteger('traccar_id')->nullable(); // Traccar's numeric device id
            $table->string('unique_id', 64);                       // Traccar uniqueId (IMEI / serial)
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();

            $table->unique('device_id', 'uq_traccar_devices_device');
            $table->unique('unique_id', 'uq_traccar_devices_unique_id');
            $table->index('traccar_id', 'idx_traccar_devices_traccar_id');

            $table->foreign('device_id', 'fk_traccar_devices_device_id')
                ->references('id')->on('devices')
                ->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traccar_devices');
    }
};
