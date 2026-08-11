<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phase 4 (DeviceComm open pipeline): per-model relay command text so the driver
// is not hardcoded. open_command / close_command are sent verbatim as the driver
// command payload (e.g. VL110C 'RELAY,1#'/'RELAY,0#', UMKa 'OUTPUT0=1'/'OUTPUT0=0').
// command_result_confirm marks models (GT06/VL110C) whose actuation is confirmed by
// polling Traccar's `commandResult` events (the device's 0x21 response text); models
// left false (UMKa/Wialon) keep the telemetry output-bit read-back via the webhook.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_models', function (Blueprint $table) {
            $table->string('open_command', 40)->nullable()->after('sms_open_command');
            $table->string('close_command', 40)->nullable()->after('open_command');
            $table->boolean('command_result_confirm')->default(false)->after('close_command');
        });
    }

    public function down(): void
    {
        Schema::table('device_models', function (Blueprint $table) {
            $table->dropColumn(['open_command', 'close_command', 'command_result_confirm']);
        });
    }
};
